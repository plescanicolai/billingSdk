<?php

namespace BillercentralSdk\BillingBundle\Service;

use BillercentralSdk\BillingBundle\Constant\Billing;
use Feedify\BaseBundle\Entity\Management\Customer;
use Symfony\Component\Form\Form;

/**
 * Class Contracts
 * @package BillercentralSdk\BillingBundle\Service
 */
class Contracts
{
    /** @var  Auth */
    public $authService;

    /**
     * Constructor
     *
     * @param Auth $authService
     */
    public function __construct(Auth $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @param array $parameters
     * @param bool  $typeExport
     * @return bool|\stdClass
     */
    public function getContracts(array $parameters = [], $typeExport = false)
    {
        if (!$this->authService->accessToken) {
            if (!$this->authService->setConfigDefault()->auth()) {
                return false;
            }
        }
        $options['query'] = $parameters + ['access_token' => $this->authService->accessToken];

        $contracts = $this->authService->getClient()->get(
            Billing::CONTRACT_PATH,
            $this->authService->getConfig() + $options
        );

        if ($contracts->getStatusCode() === 200) {
            $this->authService->initLastLogs();

            return json_decode($contracts->getBody()->getContents(), $typeExport);
        } else {
            $this->authService->setLastLogs($contracts);

            return false;
        }
    }

    /**
     * @return bool|\stdClass
     */
    public function getContractsFilters()
    {
        if (!$this->authService->accessToken) {
            if (!$this->authService->setConfigDefault()->auth()) {
                return false;
            }
        }
        $options = ['query' => ['access_token' => $this->authService->accessToken]];

        $response = $this->authService->getClient()->get(
            Billing::CONTRACT_FILTERS_PATH,
            $this->authService->getConfig() + $options
        );

        if ($response->getStatusCode() === 200) {
            $this->authService->initLastLogs();

            return json_decode($response->getBody()->getContents());
        } else {
            $this->authService->setLastLogs($response);
        }

        return false;
    }

    /**
     * @param int $contractId
     * @return bool|\stdClass
     */
    public function getContract($contractId)
    {
        if (!$this->authService->accessToken) {
            if (!$this->authService->setConfigDefault()->auth()) {
                return false;
            }
        }
        $options = ['query' => ['access_token' => $this->authService->accessToken]];

        $contract = $this->authService->getClient()->get(
            Billing::CONTRACT_PATH.Billing::API_DELIMITER.$contractId,
            $this->authService->getConfig() + $options
        );

        if ($contract->getStatusCode() === 200) {
            $this->authService->initLastLogs();

            return json_decode($contract->getBody()->getContents());
        } else {
            $this->authService->setLastLogs($contract);
        }

        return false;
    }

    /**
     * @param Customer $customer
     * @param Form     $form
     * @return bool
     */
    public function createContract(Customer $customer, $form = null)
    {
        if ($this->authService->setConfigDefault()->auth()) {
            if ($this->getContract($customer->getBillerfoxContractId())) {
                return $this->updateContract($customer, $form, true);
            }
            $this->authService->logMessage('STARTING to CREATE a new user');

            $options['form_params'] = $this->prepareCustomerData($customer) +
                ['access_token' => $this->authService->accessToken];

            $contract = $this->authService->getClient()->post(
                Billing::CONTRACT_PATH,
                $this->authService->getConfig() + $options
            );

            if ($contract->getStatusCode() === 201) {
                $this->authService->initLastLogs();
                $this->authService->logMessage('Customer was created successfully. Contract Id: '.$contract
                        ->getHeader('resource_id')[0], null, null, '', false, true);

                return $contract->getHeader('resource_id')[0];
            } else {
                $contractResponseContent = json_decode($contract->getBody()->getContents());
                $this->authService->setFormErrors($form, $contractResponseContent);
                $this->authService->logMessage('Contract Id was not returned and user was not created: ', $customer, $form
                    ->getErrorsAsString(), '', false, true);
                $this->authService->setLastLogs($contract);
            }
        }

        return false;
    }

    /**
     * @param Customer $customer
     * @param Form     $form
     * @param bool     $lastLogLine
     * @return bool
     */
    public function updateContract(Customer $customer, $form = null, $lastLogLine = false)
    {
        if (!$this->authService->accessToken) {
            $this->authService->setConfigDefault()->auth();
        }
        if (!$customer->getBillerfoxContractId()) {
            return $this->createContract($customer, $form);
        }
        $this->authService->logMessage('STARTING to UPDATE user data');

        $options['form_params'] = $this->prepareCustomerData($customer) +
            ['access_token' => $this->authService->accessToken];

        $contract = $this->authService->getClient()->put(
            Billing::CONTRACT_PATH.'/'.$customer->getBillerfoxContractId(),
            $this->authService->getConfig() + $options
        );

        if ($contract->getStatusCode() === 204) {
            $this->authService->initLastLogs();
            $this->authService->logMessage('Customer was UPDATED successfully. Contract Id: '.$contract
                    ->getHeader('resource_id')[0], null, null, '', false, $lastLogLine);

            return $contract->getHeader('resource_id')[0];
        } else {
            $contractResponseContent = json_decode($contract->getBody()->getContents());
            $this->authService->setFormErrors($form, $contractResponseContent);
            $this->authService->logMessage('Contract Id was not returned and user was not created: ', $customer, $form
                ->getErrorsAsString(), '', false, $lastLogLine);
            $this->authService->setLastLogs($contract);
        }

        return false;
    }

    /**
     * @param int $contractId
     * @return bool
     */
    public function deleteContract($contractId)
    {
        if (!$this->authService->accessToken) {
            $this->authService->setConfigDefault()->auth();
        }
        $options = ['form_params' => ['access_token' => $this->authService->accessToken]];

        $response = $this->authService->getClient()->delete(
            Billing::CONTRACT_PATH.Billing::API_DELIMITER.$contractId,
            $this->authService->getConfig() + $options
        );

        if ($response->getStatusCode() === 204) {
            $this->authService->initLastLogs();

            return true;
        } else {
            $this->authService->setLastLogs($response);

            return false;
        }
    }


    /**
     * @param Customer $customer
     * @return mixed
     */
    private function prepareCustomerData(Customer $customer)
    {
        /** @var Customer $customer */
        $data = [
            'external_id' => $customer->getUsername(),
            'gender' => $customer->getSalutation() == 1 ? 'M' : 'F',
            'company' => $customer->getCompany(),
            'address' => $customer->getStreet().'-'.$customer->getStreetNr(),
            'first_name' => $customer->getFirstName(),
            'last_name' => $customer->getLastName(),
            'zip_code' => $customer->getPostCode(),
            'city' => $customer->getCity(),
            'phone_number' => $customer->getPhone(),
            'email' => $customer->getEmail(),
            'status' => $customer->getIsActive() ? 'active' : 'blocked',
//            'termination_date' => '2016-05-18',
            'country' => $customer->getCountry()->getId(),
        ];

        if ($customer->getAccountHolder() && $customer->getAccountNumber()) {
            $data['payment_data']['bank_account'] = [
                'account_holder' => $customer->getAccountHolder(),
                'account_number' => $customer->getAccountNumber(),
                'bank_name' => $customer->getBankName(),
                'bank_code' => $customer->getBankCode(),
            ];
        } elseif ($customer->getCreditCardOwner() && $customer->getCreditCardNumber()) {
            $data['payment_data']['credit_card'] = [
                'card_number'    => $customer->getCreditCardNumber(),
                'card_type'      => $customer->getCreditCardType(),
                'card_holder'    => $customer->getCreditCardOwner(),
                'card_cvc_holder'       => $customer->getSecurityCode(),
                'card_expiration_date'  => $customer->getExpirationMonth().'/'.$customer->getExpirationYear(),
            ];
        } elseif ($customer->getBankSwift() && $customer->getBankIban()) {
            $data['payment_data']['sepa_direct_debit'] = [
                'bic' => $customer->getBankSwift(),
                'iban' => $customer->getBankIban(),
            ];
        }

        return $data;
    }
}
