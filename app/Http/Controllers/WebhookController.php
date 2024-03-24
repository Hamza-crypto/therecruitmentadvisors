<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetsService;
use Exception;
use Google\Service\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\Invoice;
use Illuminate\Support\Facades\File;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use Revolution\Google\Sheets\Facades\Sheets;

class WebhookController extends Controller
{
    public $qb_controller;

    public function __construct()
    {
        $this->qb_controller = new QBController();
    }

    public function webhook(Request $request)
    {
        $eventNotification = $request['eventNotifications'][0];
        $requestData = $eventNotification['dataChangeEvent']['entities'][0];

        if($requestData['name'] != 'Estimate') return;


        $estimateId = $requestData['id'];

        sleep(5);

        // Extract the estimate ID from the webhook request
        // $estimateId = 146; //13709; //$entity['id'];

        // Fetch estimate details using QuickBooks API
        $estimate = $this->qb_controller->call("estimate/{$estimateId}");

        // Extract necessary data from the estimate response
        $lineItems = $estimate['Estimate']['Line'];

        // Initialize an array to store product IDs
        $productIds = [];

        // Iterate over line items to fetch product IDs
        foreach ($lineItems as $lineItem) {
            $productId = $lineItem['SalesItemLineDetail']['ItemRef']['value'] ?? null;
            if ($productId) {
                $productIds[] = $productId;
            }
        }
// dump($productIds);
        // Fetch product details including SKUs based on product IDs
        $productDetails = $this->getProductDetails($productIds);
// dump($productDetails);
        // Generate an array of products along with SKUs
        $productsArray = $this->generateProductsArray($lineItems, $productDetails);
// dump($productsArray);
        // Push data to Google Sheet
        $this->pushToGoogleSheet($requestData, $estimate, $productsArray);

    }

    // Fetch product details including SKUs based on product IDs
    private function getProductDetails($productIds)
{
    // Wrap each product ID with single quotes
    $productIdsQuoted = array_map(function($productId) {
        return "'$productId'";
    }, $productIds);

    // Construct the query with product IDs inside quotes
    $query = "select * from Item where id in (" . implode(',', $productIdsQuoted) . ")";

    // Fetch product details using QuickBooks API
    $productDetails = $this->qb_controller->query($query);

    return $productDetails['QueryResponse']['Item'];
}

    // Generate an array of products along with SKUs
    private function generateProductsArray($lineItems, $productDetails)
    {
        $productsArray = [];

        foreach ($lineItems as $lineItem) {
            $productId = $lineItem['SalesItemLineDetail']['ItemRef']['value'] ?? null;
            $description = $lineItem['Description'] ?? '';
            $quantity = $lineItem['SalesItemLineDetail']['Qty'] ?? '';
            $rate = $lineItem['SalesItemLineDetail']['UnitPrice'] ?? '';
            $amount = $lineItem['Amount'] ?? '';

            // Find product details by ID
            $productDetail = collect($productDetails)->firstWhere('Id', $productId);

            if ($productDetail) {
                $sku = $productDetail['Sku'] ?? '';
                $materialCost = $productDetail['PurchaseCost'] ?? '';

                // Add each line item to the array with all necessary columns

                $rate_formula_75 = $rate * 0.75;
                $rate_number_75 = $rate_formula_75;
                $amount_75 = $quantity * $rate_formula_75;
                $net_to_vendor = $amount_75 - $materialCost;

                $productsArray[] = [
                    "PRODUCT/SERVICE" => $productDetail['Name'] ?? '',
                    "SKU" => $sku,
                    "DESCRIPTION" => $description,
                    "QTY" => sprintf("%s", $quantity),
                    "RATE" => $rate,
                    "AMOUNT" => $amount,
                    // "75% RATE FORMULA" => $rate_formula_75,
                    "75% RATE" => $rate_number_75,
                    "75% AMOUNT" => $amount_75,
                    "MATERIAL COST" => $materialCost * $quantity,
                    "NET TO VENDOR" => $net_to_vendor
                ];
        }
        }

        return $productsArray;
    }

    // Push data to Google Sheet
    private function pushToGoogleSheet($requestData, $estimate, $data)
    {
        $operation = $requestData['operation'];

        $spreadsheetId = env('SPREADSHEET_ID');

        $sheetTitle = $estimate['Estimate']['DocNumber'] . "_" . $estimate['Estimate']['Id']; // . "_" . time();


        $headerRow = [
                    "PRODUCT/SERVICE" => "PRODUCT/SERVICE",
                    "SKU" => "SKU",
                    "DESCRIPTION" => "DESCRIPTION",
                    "QTY" => "QTY",
                    "RATE" => "RATE",
                    "AMOUNT" => "AMOUNT",
                    // "75% RATE FORMULA" => "75% RATE FORMULA",
                    "75% RATE" => "75% RATE",
                    "75% AMOUNT" => "75% AMOUNT",
                    "MATERIAL COST" => "MATERIAL COST",
                    "NET TO VENDOR" => "NET TO VENDOR"
                ];


        if($operation == 'Create'){
            // Create new tab with name as sheetTitle and insert data
            $sheet = Sheets::spreadsheet($spreadsheetId)->addSheet($sheetTitle);
            array_unshift($data, $headerRow);

            $sheet = Sheets::spreadsheet($spreadsheetId)->sheet($sheetTitle);
            $sheet->append($data);
        }
        elseif($operation == 'Update'){
            try{
                try{
                    // Delete existing tab if present
                    Sheets::spreadsheet($spreadsheetId)->deleteSheet($sheetTitle);
                    sleep(5);
                    $sheet = Sheets::spreadsheet($spreadsheetId)->addSheet($sheetTitle);
                }
                catch(Exception $e){
                    $sheet = Sheets::spreadsheet($spreadsheetId)->addSheet($sheetTitle);
                }

                array_unshift($data, $headerRow);

                $sheet = Sheets::spreadsheet($spreadsheetId)->sheet($sheetTitle);
                $sheet->append($data);
        }
            catch(Exception $e){
                dump($e->getMessage());
            }
        }

        echo "Data Inserted Successfully";
    }
}