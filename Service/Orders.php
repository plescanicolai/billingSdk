<?php

namespace BillercentralSdk\BillingBundle\Service;

use BillercentralSdk\BillingBundle\Constant\Billing;
use Feedify\BaseBundle\Entity\Management\Customer;

/**
 * Class Orders
 * @package BillercentralSdk\BillingBundle\Service
 */
class Orders
{
    /** @var  Auth $authService */
    public $authService;

    /**
     * @param Auth $authService
     */
    public function __construct(Auth $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @param int  $idContract
     * @param bool $assoc
     * @return bool
     */
    public function getOrdersForOneContract($idContract, $assoc = false)
    {
        $this->authService->logMessage('STARTING to UPDATE order data');
        if (!$this->authService->accessToken) {
            $this->authService->setConfigDefault()->auth();
        }
        $options = ['query' => ['access_token' => $this->authService->accessToken]];

        $order = $this->authService->getClient()->get(
            Billing::CONTRACT_PATH.Billing::API_DELIMITER.$idContract.Billing::ADD_ORDERS_PATH,
            $this->authService->getConfig() + $options
        );

        if ($order->getStatusCode() == 200) {
            return json_decode($order->getBody()->getContents(), $assoc);
        } else {
            $contractResponseContent = json_decode($order->getBody()->getContents());
            $this->authService->setLastLogs($order);
        }

        return false;
    }

    /**
     * @param Customer $customer
     * @return bool
     */
    public function createOrderForContract($customer)
    {
        //check if user have one order item
        $this->authService->logMessage('Start add order for contractId '.$customer->getBillerfoxContractId());
        if (!$this->authService->accessToken) {
            $this->authService->setConfigDefault()->auth();
        }
        if (($orders = $this->getOrdersForOneContract($customer->getBillerfoxContractId(), true)) && ((int) $orders['count'] != 0)) {
            if (isset($orders['items'])) {
                foreach ($orders['items'] as $order) {
                    if ((isset($order['order_items'])) && (count($order['order_items'])) > 0) {
                        if (isset($order['order_items'][0]['id']) && $orderItemId = $order['order_items'][0]['id']) {
                            $options['form_params'] = ['access_token' => $this->authService->accessToken];

                            $response = $this->authService->getClient()->delete(
                                Billing::UPDATE_ORDERS_PATH.Billing::API_DELIMITER.$order['id'].Billing::UPDATE_ITEMS_PATH.Billing::API_DELIMITER.$orderItemId,
                                $this->authService->getConfig() + $options
                            );
                        }
                    }
                }
            }
        }
        $options['form_params'] = $this->prepareCustomerDataForOrder($customer) +
            ['access_token' => $this->authService->accessToken];

        $order = $this->authService->getClient()->post(
            Billing::CONTRACT_PATH.Billing::API_DELIMITER.$customer->getBillerfoxContractId().Billing::ADD_ORDERS_PATH,
            $this->authService->getConfig() + $options
        );

        if ($order->getStatusCode() == 201) {
            $this->authService->initLastLogs();
            $this->authService->logMessage('Customer was created successfully. Order Id: '.$order
                    ->getHeader('resource_id')[0], null, null, '', false, true);

            return [];
        } else {
            $orderResponseContent = json_decode($order->getBody()->getContents());
            $this->authService->logMessage('Contract Id was not returned and user was not created: ', $customer, '', '', false, true);
            $this->authService->setLastLogs($order);
        }

        return ['Create order error: '.$orderResponseContent];
    }


    /**
     * @param Customer $customer
     * @return array
     */
    private function prepareCustomerDataForOrder(Customer $customer)
    {
        return [
            'external_id' => $customer->getUsername(),
            'active' => true,
            'order_items' => [[
                'plan' => (int) $customer->getTariff(),
                'active' => true,
            ],
            ],
        ];
    }
}
