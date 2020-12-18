<?php
ini_set('max_execution_time', 0);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

$client = new \RetailCrm\ApiClient(
    $url,
    $apiKey,
    \RetailCrm\ApiClient::V5
);

$_GET['id'] = 1727;

$customerGet = $client->request->customersGet($_GET['id'], 'id');
$filter = [
    'contragentTypes' => [
        'individual'
    ]
];
$doublesByPhone = getDoublesByPhone($client, $customerGet['customer'], $filter);
$doublesByEmail = getDoublesByEmail($client, $customerGet['customer'], $filter);
$doublesArray = deleteCopies(array_merge($doublesByPhone, $doublesByEmail, [
    0 => [
        'id' => $customerGet['customer']['id'],
        'date' => $customerGet['customer']['createdAt'],
    ]
]));
$resultCustomer = findMinByDate($doublesArray);
$combineCustomers = [];
$combineCustomersToRequest = [];
foreach ($doublesArray as $customer) {
    if ($customer['id'] != $resultCustomer['id']) {
        $combineCustomers[]['id'] = $customer['id'];
        $combineCustomersToRequest[] = $customer['id'];
    }
}
$combineCustomersToRequest[] = $resultCustomer['id'];
$customersList = $client->request->customersList([
    'ids' => $combineCustomersToRequest
], 1, 100);
$fields = fillEmptyFields($customersList['customers']);
$fields['id'] = $resultCustomer['id'];
$combineAction = $client->request->customersCombine($combineCustomers, $resultCustomer);
logger('combine.log', $combineAction);

if (!$combineAction->isSuccessful()) {
    die;
}
$orderEdit = $client->request->customersEdit($fields, 'id','santeh-lab-ru');
logger('customerEdit.log', $orderEdit);




