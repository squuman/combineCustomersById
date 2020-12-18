<?php

function logger($filename = 'file.log', $data = array())
{
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs');
    }
    $fd = fopen(__DIR__ . '/logs/' . $filename, 'a');
    fwrite($fd, print_r($data, true));
    fclose($fd);
}

function combine($client, $result, $customer)
{
    $filledFields = [];
    foreach ($customer as $customerKey => $customerField) {
        if (!isset($result[$customerKey]) or $result[$customerKey] == '') {
            if (isset($customer[$customerKey]) and $customer[$customerKey] != '') {
                $filledFields[$customerKey] = $customerField;
            }
        }
    }
    foreach ($result as $resultKey => $resultField) {
        if (!isset($customer[$resultKey]) or $customer[$resultKey] == '') {
            if (isset($customer[$result]) and $result[$customerKey] != '') {
                $filledFields[$resultKey] = $resultField;
            }
        }
    }
    $combineAction = $client->request->customersCombine([
        0 => [
            'id' => $result['id']
        ]
    ], [
        'id' => $customer['id']
    ]);
    $filledFields['id'] = $result['id'];
    $orderEdit = $client->request->customersEdit($filledFields, 'id', $result['site']);
    logger('editCustomer.log', $orderEdit);
    logger('fields.log', $filledFields);
    logger('combineByPhone.log', $combineAction);
}

function getDoublesByEmail($client, $customerToCheck, $filter)
{
    $filter['email'] = $customerToCheck['email'];
    $combine = [];
    $customers = $client->request->customersList($filter, 1, 100);
    $totalPageCount = $customers['pagination']['totalPageCount'];
    for ($i = 1; $i <= $totalPageCount; $i++) {
        $customers = $client->request->customersList($filter, $i, 100);
        foreach ($customers['customers'] as $customer) {
            if ($customerToCheck['id'] != $customer['id']) {
                $combine[] = [
                    'id' => $customer['id'],
                    'date' => $customer['createdAt']
                ];
            }
        }
    }

    return $combine;
}

function getDoublesByPhone($client, $customerToCheck, $filter)
{
    $combine = [];
    $customers = $client->request->customersList($filter, 1, 100);
    $totalPageCount = $customers['pagination']['totalPageCount'];
    for ($i = 1; $i <= $totalPageCount; $i++) {
        $customers = $client->request->customersList($filter, $i, 100);
        foreach ($customers['customers'] as $customer) {
            if ($customerToCheck['id'] == $customer['id']) {
                continue;
            }

            foreach ($customer['phones'] as $phone) {
                foreach ($customerToCheck['phones'] as $phoneToCheck) {
                    if (formatPhones($phoneToCheck['number']) == formatPhones($phone['number'])) {
                        $combine[] = [
                            'id' => $customer['id'],
                            'date' => $customer['createdAt']
                        ];
                    }
                }
            }
        }


    }
    return $combine;
}

function deleteCopies($array)
{
    $arrayToCheck = $array;
    foreach ($arrayToCheck as $key => &$item) {
        foreach ($arrayToCheck as $keyCopy => &$itemCopy) {
            if ($key == $keyCopy) {
                continue;
            }

            if ($item['id'] == $itemCopy['id']) {
                unset($arrayToCheck[$keyCopy]);
            }
        }
    }

    return $arrayToCheck;
}

function findMinByDate($array)
{
    $minValue = $array[0];
    foreach ($array as $key => &$item) {
        if (strtotime($item['date']) < strtotime($minValue['date'])) {
            $minValue = $item;
        }
    }
    unset($minValue['date']);
    return $minValue;
}

function formatPhones($phoneNumber)
{
    return str_replace('+7', '', str_replace(['(', ')', '-', ' '], '', $phoneNumber));
}

function fillEmptyFields($customersArray = [])
{
    $fields = [];
    foreach ($customersArray as $customer) {
        foreach ($customersArray as $customerToCheck) {
            foreach ($customer as $fieldKey => $fieldValue) {
                if (is_array($fieldValue)) {
                    foreach ($fieldValue as $subFieldKey => $subFieldValue) {
                        if (!isset($customerToCheck[$fieldKey][$subFieldKey]) or
                            $customerToCheck[$fieldKey][$subFieldKey] == '' or
                            empty($customerToCheck[$fieldKey][$subFieldKey])) {
                            $fields[$fieldKey][$subFieldKey] = $subFieldValue;
                        }
                    }
                } elseif (!isset($customerToCheck[$fieldKey]) or $customerToCheck[$fieldKey] == '' or
                    empty($customerToCheck[$fieldKey])) {
                    $fields[$fieldKey] = $fieldValue;
                }
            }
        }

    }

    return $fields;
}