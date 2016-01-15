<?php

namespace BillercentralSdk\BillingBundle\Service;

use BillercentralSdk\BillingBundle\Constant\Billing;
use Feedify\BaseBundle\Entity\Management\Customer;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;

/**
 * Class Auth
 * @package BillercentralSdk\BillingBundle\Service
 */
class Auth
{
    /** @var array */
    private $credentials;

    /** @var Client */
    private $client;

    /** @var array */
    private $config;

    /** @var string */
    public $accessToken;

    /** @var string */
    public $refreshToken;

    /** @var int */
    public $expiresIn;

    /** @var \DateTime */
    public $startedTo;

    /** @var int */
    public $statusCode;

    /** @var string */
    public $message;

    /** @var array|\stdClass */
    public $errors;

    public $logger;

    protected $rootDirectory;

    /**
     * Constructor
     *
     * @param array  $credentials
     * @param string $rootDirectory
     */
    public function __construct($credentials, $rootDirectory)
    {
        $this->credentials = $credentials;
        $this->rootDirectory = $rootDirectory;

        //Create new GuzzleHttp Client
        $this->client = new Client(array('base_uri' => Billing::BASE_URL));

        $this->initConfig();
    }

    /**
     * Initialize last logs
     */
    public function initLastLogs()
    {
        $this->statusCode = $this->message = $this->errors = null;
    }

    /**
     * Set last logs
     *
     * @param Response|ResponseInterface $response
     */
    public function setLastLogs($response)
    {
        $this->statusCode = $response->getStatusCode();
        $content = json_decode($response->getBody()->getContents());
        $this->message = isset($content->message) ? $content->message : null;
        $this->errors = isset($content->errors) ? $content->errors : null;
    }

    /**
     * Initialize config for request
     */
    public function initConfig()
    {
        $this->config = array();
    }

    /**
     * Function that transfers HTTP requests over the wire.
     * @link http://docs.guzzlephp.org/en/latest/quickstart.html#creating-a-client
     *
     * @param callable $handler
     * @return $this
     */
    public function setConfigHandler($handler)
    {
        $this->config['handler'] = $handler;

        return $this;
    }

    /**
     * Describes the redirect behavior of a request.
     * By default are set by Guzzle: (redirect middleware)
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#allow-redirects
     *
     * @param bool|array $redirects
     * @return $this
     */
    public function setConfigRedirects($redirects = false)
    {
        $this->config['allow_redirects'] = $redirects;

        return $this;
    }

    /**
     * Specifies whether or not cookies are used in a request or what cookie jar to use or what cookies to send.
     * By default are set by Guzzle: (false)
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#cookies
     *
     * @param bool $cookies
     * @return $this
     */
    public function setConfigCookies($cookies = false)
    {
        $this->config['cookies'] = $cookies;

        return $this;
    }

    /**
     * Float describing the timeout of the request in seconds.
     * By default are set by Guzzle: (0)
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#timeout
     *
     * @param float $timeout
     * @return $this
     */
    public function setConfigTimeout($timeout = 0.0)
    {
        $this->config['timeout'] = $timeout;

        return $this;
    }

    /**
     * Float describing the number of seconds to wait while trying to connect to a server.
     * By default are set by Guzzle: (0)
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#connect-timeout
     *
     * @param float $connectTimeout
     * @return $this
     */
    public function setConfigConnectTimeout($connectTimeout = 0.0)
    {
        $this->config['connect_timeout'] = $connectTimeout;

        return $this;
    }

    /**
     * Specify whether or not Content-Encoding responses (gzip, deflate, etc.) are automatically decoded.
     * By default are set by Guzzle: (true)
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#decode-content
     *
     * @param bool|string $decodeContent
     * @return $this
     */
    public function setConfigDecodeContent($decodeContent = true)
    {
        $this->config['decode_content'] = $decodeContent;

        return $this;
    }

    /**
     * The number of milliseconds to delay before sending the request.
     * By default are set by Guzzle: (null)
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#delay
     *
     * @param integer|float $delay
     * @return $this
     */
    public function setConfigDelay($delay = null)
    {
        $this->config['delay'] = $delay;

        return $this;
    }

    /**
     * Exceptions are thrown by default when HTTP protocol errors are encountered.
     * By default are set by Guzzle: (true)
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#http-errors
     *
     * @param bool $errors
     * @return $this
     */
    public function setConfigErrors($errors = false)
    {
        $this->config['http_errors'] = $errors;

        return $this;
    }

    /**
     * Associative array of headers to add to the request.
     * @link http://docs.guzzlephp.org/en/latest/request-options.html#headers
     *
     * @param array $headers
     * @return $this
     */
    public function setConfigHeaders(array $headers = array())
    {
        $this->config['headers'] = $headers;

        return $this;
    }

    /**
     * Initialize config parameters and set it by default
     *
     * @return $this
     */
    public function setConfigDefault()
    {
        $this->initConfig();
        $this
            ->setConfigRedirects(false)
            ->setConfigErrors(false)
            ->setConfigHeaders(array('Accept' => 'application/json'));

        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Authentication on Billercentral API
     *
     * @return bool
     */
    public function auth()
    {
        $options = [
            'form_params' => [
                'grant_type'    => 'password',
                'company'       => $this->credentials['company'],
                'username'      => $this->credentials['username'],
                'password'      => $this->credentials['password'],
                'client_id'     => $this->credentials['client_id'],
                'client_secret' => $this->credentials['client_secret'],
            ],
        ];

        $response = $this->client->post(Billing::AUTH_PATH, $this->config + $options);

        if ($response->getStatusCode() == 200) {
            $content = json_decode($response->getBody()->getContents(), true);
            isset($content['access_token']) && $this->accessToken = $content['access_token'];
            isset($content['refresh_token']) && $this->refreshToken = $content['refresh_token'];
            if (isset($content['expires_in'])) {
                $this->expiresIn = $content['expires_in'];
                $this->startedTo = new \DateTime();
            }

            // Initialize last logs (all are good)
            $this->initLastLogs();
            $this->logMessage('Connected successful at '.date('d-m-y : H-i-s'), null, null, $options['form_params'], true);

            return true;
        } else {
            // Set last logs data
            $this->setLastLogs($response);
            $this->logMessage('Connected failed '.date('d-m-y : H-i-s'), null, null, $options['form_params'], true);

            return false;
        }
    }

    /**
     * Refresh authentication token
     */
    public function refreshAuth()
    {
        //todo...
    }

    /**
     * @param Form   $form
     * @param object $contract
     * @return Form
     */
    public function setFormErrors($form, $contract)
    {
        if (isset($contract->errors->children) && $errors = $contract->errors->children) {
            foreach ($errors as $key => $error) {
                if (is_object($error) && isset($error->errors[0])) {
                    $form->addError(new FormError(isset($this->getErrorsMessage()[$key]) ? $this->getErrorsMessage()[$key] : 'Something is wrong'));
                }
            }
        } else {
            $form->addError(new FormError('Something is wrong'));
        }

        return $form;
    }

    /**
     * @param string   $message
     * @param Customer $customer
     * @param null     $contractErrorsMessage
     * @param string   $additional
     * @param bool     $firstMessage
     * @param bool     $lastMessage
     */
    public function logMessage($message, Customer $customer = null, $contractErrorsMessage = null, $additional = '', $firstMessage = false, $lastMessage = false)
    {
        $log = $this->initBillerfoxLog();
        !$firstMessage ?: $log->addNotice('Start log for connexion', ['===================================START=================================']);

        /** @var Customer $customer */
        if ($customer) {
            $log->addError(
                $message,
                array('username' => $customer->getUsername(),
                    'contractId' => $customer->getBillerfoxContractId(),
                    'tariffId' => $customer->getTariff(),
                    'errors' => explode("\n", $contractErrorsMessage),
                    'additionalInfo' => $additional,
                )
            );
        } else {
            if ($additional) {
                $log->addInfo($message, array('info' => $additional));
            } else {
                $log->addInfo($message);
            }
        }
        !$lastMessage ?: $log->addNotice('Finish log connexion   ', ['***********************************FINISH********************************']);
    }

    /**
     * Create a log channel to billerfox actions
     * @return Logger
     */
    protected function initBillerfoxLog()
    {
        if (!$this->logger) {
            $this->logger = new Logger('flexcharge');
            $this->logger->pushHandler(new StreamHandler($this->rootDirectory.'/logs/flexcharge.log', Logger::INFO));
        }

        return $this->logger;
    }

    protected function getErrorsMessage()
    {
        return [
            'company' => 'flexcharge_company_error',
            'first_name' => 'flexcharge_first_name_error',
            'last_name' => 'flexcharge_last_name_error',
            'zip_code' => 'flexcharge_zip_code_error',
            'city' => 'flexcharge_city_error',
            'phone_number' => 'flexcharge_phone_number_error',
            'email' => 'flexcharge_email_error',
            'country' => 'flexcharge_country_error',
        ];
    }
}
