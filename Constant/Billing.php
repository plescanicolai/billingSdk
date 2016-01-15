<?php

namespace BillercentralSdk\BillingBundle\Constant;

/**
 * Class Billing
 * @package BillercentralSdk\BillingBundle\Constant
 */
class Billing
{
    const BASE_URL = "http://api.billercentral.com";
//    const BASE_URL = "http://localhost:8080";
    const AUTH_PATH = "/oauth2/token";

    const API_DELIMITER = "/";

    const CONTRACT_PATH = "/v1/contracts";
    const CONTRACT_FILTERS_PATH = "/v1/contracts_filters";

    const ADD_ORDERS_PATH = "/orders";
    const UPDATE_ORDERS_PATH = "/v1/orders";

    const UPDATE_ITEMS_PATH = "/items";
}
