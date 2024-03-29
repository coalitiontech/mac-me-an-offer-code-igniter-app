<?php
namespace Bigcommerce\Api;
use \Exception as Exception;
use Firebase\JWT\JWT;

/**
 * Bigcommerce API Client.
 */
 
class Client
{
    /**
     * Full Store URL to connect to
     *
     * @var string
     */
    static private $store_url;

    /**
     * Username to connect to the store API with
     *
     * @var string
     */
    static private $username;

    /**
     * API key
     *
     * @var string
     */
    static private $api_key;

    /**
     * Connection instance
     *
     * @var Connection
     */
    static private $connection;

    /**
     * Resource class name
     *
     * @var string
     */
    static private $resource;

    /**
     * API path prefix to be added to store URL for requests
     *
     * @var string
     */
    static private $path_prefix = '/api/v2';

    /**
     * Full URL path to the configured store API.
     *
     * @var string
     */
    static public $api_path;
    static private $client_id;
    static private $store_hash;
    static private $auth_token;
    static private $client_secret;
    static private $stores_prefix = '/stores/%s/v2';
    static private $api_url = 'https://api.bigcommerce.com';
    static private $login_url = 'https://login.bigcommerce.com';

    /**
     * Configure the API client with the required settings to access
     * the API for a store.
     *
     * Accepts OAuth and (for now!) Basic Auth credentials
     *
     * @param array $settings
     */
    public static function configure($settings)
    {
        if (isset($settings['client_id'])) {
            self::configureOAuth($settings);
        } else {
            self::configureBasicAuth($settings);
        }
    }

    /**
     * Configure the API client with the required OAuth credentials.
     *
     * Requires a settings array to be passed in with the following keys:
     *
     * - client_id
     * - auth_token
     * - store_hash
     *
     * @param array $settings
     * @throws \Exception
     */
    public static function configureOAuth($settings)
    {
        if (!isset($settings['auth_token'])) {
            throw new Exception("'auth_token' must be provided");
        }

        if (!isset($settings['store_hash'])) {
            throw new Exception("'store_hash' must be provided");
        }

        self::$client_id = $settings['client_id'];
        self::$auth_token = $settings['auth_token'];
        self::$store_hash = $settings['store_hash'];

        self::$client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : null;

        self::$api_path = self::$api_url . sprintf(self::$stores_prefix, self::$store_hash);
        self::$connection = false;
    }

    /**
     * Configure the API client with the required credentials.
     *
     * Requires a settings array to be passed in with the following keys:
     *
     * - store_url
     * - username
     * - api_key
     *
     * @param array $settings
     * @throws \Exception
     */
    public static function configureBasicAuth(array $settings)
    {
        if (!isset($settings['store_url'])) {
            throw new Exception("'store_url' must be provided");
        }

        if (!isset($settings['username'])) {
            throw new Exception("'username' must be provided");
        }

        if (!isset($settings['api_key'])) {
            throw new Exception("'api_key' must be provided");
        }

        self::$username = $settings['username'];
        self::$api_key = $settings['api_key'];
        self::$store_url = rtrim($settings['store_url'], '/');
        self::$api_path = self::$store_url . self::$path_prefix;
        self::$connection = false;
    }

    /**
     * Configure the API client to throw exceptions when HTTP errors occur.
     *
     * Note that network faults will always cause an exception to be thrown.
     *
     * @param bool $option sets the value of this flag
     */
    public static function failOnError($option = true)
    {
        self::connection()->failOnError($option);
    }

    /**
     * Return XML strings from the API instead of building objects.
     */
    public static function useXml()
    {
        self::connection()->useXml();
    }

    /**
     * Return JSON objects from the API instead of XML Strings.
     * This is the default behavior.
     */
    public static function useJson()
    {
        self::connection()->useXml(false);
    }

    /**
     * Switch SSL certificate verification on requests.
     *
     * @param bool $option sets the value of this flag
     */
    public static function verifyPeer($option = false)
    {
        self::connection()->verifyPeer($option);
    }

    /**
     * Connect to the internet through a proxy server.
     *
     * @param string $host host server
     * @param int|bool $port port number to use, or false
     */
    public static function useProxy($host, $port = false)
    {
        self::connection()->useProxy($host, $port);
    }

    /**
     * Get error message returned from the last API request if
     * failOnError is false (default).
     *
     * @return string
     */
    public static function getLastError()
    {
        return self::connection()->getLastError();
    }

    /**
     * Get an instance of the HTTP connection object. Initializes
     * the connection if it is not already active.
     *
     * @return Connection
     */
    private static function connection()
    {
        if (!self::$connection) {
            self::$connection = new Connection();
            if (self::$client_id) {
                self::$connection->authenticateOauth(self::$client_id, self::$auth_token);
            } else {
                self::$connection->authenticateBasic(self::$username, self::$api_key);
            }
        }

        return self::$connection;
    }

    /**
     * Convenience method to return instance of the connection
     *
     * @return Connection
     */
    public static function getConnection()
    {
        return self::connection();
    }

    /**
     * Set the HTTP connection object. DANGER: This can screw up your Client!
     *
     * @param Connection $connection The connection to use
     */
    public static function setConnection(Connection $connection = null)
    {
        self::$connection = $connection;
    }

    /**
     * Get a collection result from the specified endpoint.
     *
     * @param string $path api endpoint
     * @param string $resource resource class to map individual items
     * @return mixed array|string mapped collection or XML string if useXml is true
     */
    public static function getCollection($path, $resource = 'Resource')
    {
        $response = self::connection()->get(self::$api_path . $path);

        return self::mapCollection($resource, $response);
    }

    /**
     * Get a resource entity from the specified endpoint.
     *
     * @param string $path api endpoint
     * @param string $resource resource class to map individual items
     * @return mixed Resource|string resource object or XML string if useXml is true
     */
    public static function getResource($path, $resource = 'Resource')
    {
        $response = self::connection()->get(self::$api_path . $path);

        return self::mapResource($resource, $response);
    }

    /**
     * Get a count value from the specified endpoint.
     *
     * @param string $path api endpoint
     * @return mixed int|string count value or XML string if useXml is true
     */
    public static function getCount($path)
    {
        $response = self::connection()->get(self::$api_path . $path);

        if ($response == false || is_string($response)) {
            return $response;
        }

        return $response->count;
    }

    /**
     * Send a post request to create a resource on the specified collection.
     *
     * @param string $path api endpoint
     * @param mixed $object object or XML string to create
     * @return mixed
     */
    public static function createResource($path, $object)
    {
        if (is_array($object)) {
            $object = (object)$object;
        }

        return self::connection()->post(self::$api_path . $path, $object);
    }

    /**
     * Send a put request to update the specified resource.
     *
     * @param string $path api endpoint
     * @param mixed $object object or XML string to update
     * @return mixed
     */
    public static function updateResource($path, $object)
    {
        if (is_array($object)) {
            $object = (object)$object;
        }

        return self::connection()->put(self::$api_path . $path, $object);
    }

    /**
     * Send a delete request to remove the specified resource.
     *
     * @param string $path api endpoint
     * @return mixed
     */
    public static function deleteResource($path)
    {
        return self::connection()->delete(self::$api_path . $path);
    }

    /**
     * Internal method to wrap items in a collection to resource classes.
     *
     * @param string $resource name of the resource class
     * @param array $object object collection
     * @return array
     */
    private static function mapCollection($resource, $object)
    {
        if ($object == false || is_string($object)) {
            return $object;
        }

        $baseResource = __NAMESPACE__ . '\\' . $resource;
        self::$resource = (class_exists($baseResource)) ? $baseResource : 'Bigcommerce\\Api\\Resources\\' . $resource;

        return array_map(array('self', 'mapCollectionObject'), $object);
    }

    /**
     * Callback for mapping collection objects resource classes.
     *
     * @param \stdClass $object
     * @return Resource
     */
    private static function mapCollectionObject($object)
    {
        $class = self::$resource;

        return new $class($object);
    }

    /**
     * Map a single object to a resource class.
     *
     * @param string $resource name of the resource class
     * @param \stdClass $object
     * @return Resource
     */
    private static function mapResource($resource, $object)
    {
        if ($object == false || is_string($object)) {
            return $object;
        }

        $baseResource = __NAMESPACE__ . '\\' . $resource;
        $class = (class_exists($baseResource)) ? $baseResource : 'Bigcommerce\\Api\\Resources\\' . $resource;

        return new $class($object);
    }

    /**
     * Map object representing a count to an integer value.
     *
     * @param \stdClass $object
     * @return int
     */
    private static function mapCount($object)
    {
        if ($object == false || is_string($object)) {
            return $object;
        }

        return $object->count;
    }

    /**
     * Swaps a temporary access code for a long expiry auth token.
     *
     * @param \stdClass $object
     * @return \stdClass
     */
    public static function getAuthToken($object)
    {
        $context = array_merge(array('grant_type' => 'authorization_code'), (array)$object);
        $connection = new Connection();
        $connection->useUrlEncoded();

        return $connection->post(self::$login_url . '/oauth2/token', $context);
    }

    public static function getCustomerLoginToken($id, $redirectUrl = '', $requestIp = '')
    {
        if (empty(self::$client_secret)) {
            throw new Exception('Cannot sign customer login tokens without a client secret');
        }

        $payload = array(
            'iss' => self::$client_id,
            'iat' => time(),
            'jti' => bin2hex(random_bytes(32)),
            'operation' => 'customer_login',
            'store_hash' => self::$store_hash,
            'customer_id' => $id
        );

        if (!empty($redirectUrl)) {
            $payload['redirect_to'] = $redirectUrl;
        }

        if (!empty($requestIp)) {
            $payload['request_ip'] = $requestIp;
        }

        return JWT::encode($payload, self::$client_secret, 'HS256');
    }

    /**
     * Pings the time endpoint to test the connection to a store.
     *
     * @return \DateTime
     */
    public static function getTime()
    {
        $response = self::connection()->get(self::$api_path . '/time');

        if ($response == false || is_string($response)) {
            return $response;
        }

        return new \DateTime("@{$response->time}");
    }

    /**
     * Returns the default collection of products.
     *
     * @param array $filter
     * @return mixed array|string list of products or XML string if useXml is true
     */
    public static function getProducts($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/products' . $filter->toQuery(), 'Product');
    }

    /**
     * Gets collection of images for a product.
     *
     * @param int $id product id
     * @return mixed array|string list of products or XML string if useXml is true
     */
    public static function getProductImages($id)
    {
        return self::getCollection('/products/' . $id . '/images', 'ProductImage');
    }

    /**
     * Gets collection of custom fields for a product.
     *
     * @param int $id product ID
     * @return array|string list of products or XML string if useXml is true
     */
    public static function getProductCustomFields($id)
    {
        return self::getCollection('/products/' . $id . '/customfields', 'ProductCustomField');
    }
    
     public static function createBulkPrice($product_id, $object)
    {
        return self::createResource('/products/' . $product_id . '/discount_rules', $object);
    }

    /**
     * Returns a single custom field by given id
     * @param  int $product_id product id
     * @param  int $id custom field id
     * @return Resources\ProductCustomField|bool Returns ProductCustomField if exists, false if not exists
     */
    public static function getProductCustomField($product_id, $id)
    {
        return self::getResource('/products/' . $product_id . '/customfields/' . $id, 'ProductCustomField');
    }

    /**
     * Create a new custom field for a given product.
     *
     * @param int $product_id product id
     * @param mixed $object fields to create
     * @return Object Object with `id`, `product_id`, `name` and `text` keys
     */
    public static function createProductCustomField($product_id, $object)
    {
        return self::createResource('/products/' . $product_id . '/customfields', $object);
    }

    /**
     * Gets collection of reviews for a product.
     *
     * @param $id
     * @return mixed
     */
    public static function getProductReviews($id)
    {
        return self::getCollection('/products/' . $id . '/reviews/', 'ProductReview');
    }

    /**
     * Update the given custom field.
     *
     * @param int $product_id product id
     * @param int $id custom field id
     * @param mixed $object custom field to update
     * @return mixed
     */
    public static function updateProductCustomField($product_id, $id, $object)
    {
        return self::updateResource('/products/' . $product_id . '/customfields/' . $id, $object);
    }

    /**
     * Delete the given custom field.
     *
     * @param int $product_id product id
     * @param int $id custom field id
     * @return mixed
     */
    public static function deleteProductCustomField($product_id, $id)
    {
        return self::deleteResource('/products/' . $product_id . '/customfields/' . $id);
    }

    /**
     * Returns the total number of products in the collection.
     *
     * @param array $filter
     * @return int|string number of products or XML string if useXml is true
     */
    public static function getProductsCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/products/count' . $filter->toQuery());
    }

    /**
     * Returns a single product resource by the given id.
     *
     * @param int $id product id
     * @return Resources\Product|string
     */
    public static function getProduct($id)
    {
        return self::getResource('/products/' . $id, 'Product');
    }

    /**
     * Create a new product.
     *
     * @param mixed $object fields to create
     * @return mixed
     */
    public static function createProduct($object)
    {
        return self::createResource('/products', $object);
    }

    /**
     * Update the given product.
     *
     * @param int $id product id
     * @param mixed $object fields to update
     * @return mixed
     */
    public static function updateProduct($id, $object)
    {
        return self::updateResource('/products/' . $id, $object);
    }

    /**
     * Delete the given product.
     *
     * @param int $id product id
     * @return mixed
     */
    public static function deleteProduct($id)
    {
        return self::deleteResource('/products/' . $id);
    }
    
    public static function deleteProducts()
    {
        return self::deleteResource('/products');
    }


    /**
     * Delete all products.
     *
     * @return mixed
     */
    public static function deleteAllProducts()
    {
        return self::deleteResource('/products');
    }

    /**
     * Return the collection of options.
     *
     * @param array $filter
     * @return array
     */
    public static function getOptions($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/options' . $filter->toQuery(), 'Option');
    }
    
     public static function getProductoptions($id)
    {
        return self::getCollection('/products/' . $id .'/options','ProductOption');
    }

    /**
     * Create Options
     * @param $object
     * @return mixed
     */
    public static function createOption($object)
    {
        return self::createResource('/options', $object);
    }
	
	public static function createOptionValue($option_id, $object)
	{
		return self::createResource("/options/" . $option_id . "/values", $object);
	}
	
	public static function createOptionRules($productid, $object)
	{
		return self::createResource("/products/" . $productid . "/rules", $object);
	}
	
	public static function getOptionRules($id)
	{
		 return self::getResource('/products/' . $id .'/rules');
	}
    /**
     * Update the given option.
     *
     * @param int $id category id
     * @param mixed $object
     * @return mixed
     */
    public static function updateOption($id, $object)
    {
        return self::updateResource('/options/' . $id, $object);
    }

    /**
     * Return the number of options in the collection
     *
     * @return int
     */
    public static function getOptionsCount()
    {
        return self::getCount('/options/count');
    }

    /**
     * Return a single option by given id.
     *
     * @param int $id option id
     * @return Resources\Option
     */
    public static function getOption($id)
    {
        return self::getResource('/options/' . $id, 'Option');
    }

    /**
     * Delete the given option.
     *
     * @param int $id option id
     * @return mixed
     */
    public static function deleteOption($id)
    {
        return self::deleteResource('/options/' . $id);
    }

    /**
     * Return a single value for an option.
     *
     * @param int $option_id option id
     * @param int $id value id
     * @return Resources\OptionValue
     */
    public static function getOptionValue($option_id, $id)
    {
        return self::getResource('/options/' . $option_id . '/values/' . $id, 'OptionValue');
    }
	
	public static function getOptionCValue($option_id)
    {
        return self::getCollection('/options/' . $option_id . '/values');
    }
	

    /**
     * Return the collection of all option values.
     *
     * @param array $filter
     * @return array
     */
    public static function getOptionValues($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/options/values' . $filter->toQuery(), 'OptionValue');
    }

    /**
     * The collection of categories.
     *
     * @param array $filter
     * @return array
     */
    public static function getCategories($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/categories' . $filter->toQuery(), 'Category');
    }

    /**
     * The number of categories in the collection.
     *
     * @param array $filter
     * @return int
     */
    public static function getCategoriesCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/categories/count' . $filter->toQuery());
    }

    /**
     * A single category by given id.
     *
     * @param int $id category id
     * @return Resources\Category
     */
    public static function getCategory($id)
    {
        return self::getResource('/categories/' . $id, 'Category');
    }

    /**
     * Create a new category from the given data.
     *
     * @param mixed $object
     * @return mixed
     */
    public static function createCategory($object)
    {
        return self::createResource('/categories', $object);
    }
    
    public static function createProductSKUS($id, $object)
	{
		return self::createResource("/products/" . $id . "/skus", $object);
	}
	

    /**
     * Update the given category.
     *
     * @param int $id category id
     * @param mixed $object
     * @return mixed
     */
    public static function updateCategory($id, $object)
    {
        return self::updateResource('/categories/' . $id, $object);
    }

    /**
     * Delete the given category.
     *
     * @param int $id category id
     * @return mixed
     */
    public static function deleteCategory($id)
    {
        return self::deleteResource('/categories/' . $id);
    }

    /**
     * Delete all categories.
     *
     * @return mixed
     */
    public static function deleteAllCategories()
    {
        return self::deleteResource('/categories');
    }

    /**
     * The collection of brands.
     *
     * @param array $filter
     * @return array
     */
    public static function getBrands($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/brands' . $filter->toQuery(), 'Brand');
    }

    /**
     * The total number of brands in the collection.
     *
     * @param array $filter
     * @return int
     */
    public static function getBrandsCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/brands/count' . $filter->toQuery());
    }

    /**
     * A single brand by given id.
     *
     * @param int $id brand id
     * @return Resources\Brand
     */
    public static function getBrand($id)
    {
        return self::getResource('/brands/' . $id, 'Brand');
    }

    /**
     * Create a new brand from the given data.
     *
     * @param mixed $object
     * @return mixed
     */
    public static function createBrand($object)
    {
        return self::createResource('/brands', $object);
    }

    /**
     * Update the given brand.
     *
     * @param int $id brand id
     * @param mixed $object
     * @return mixed
     */
    public static function updateBrand($id, $object)
    {
        return self::updateResource('/brands/' . $id, $object);
    }

    /**
     * Delete the given brand.
     *
     * @param int $id brand id
     * @return mixed
     */
    public static function deleteBrand($id)
    {
        return self::deleteResource('/brands/' . $id);
    }

    /**
     * Delete all brands.
     *
     * @return mixed
     */
    public static function deleteAllBrands()
    {
        return self::deleteResource('/brands');
    }

    /**
     * The collection of orders.
     *
     * @param array $filter
     * @return array
     */
    public static function getOrders($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders' . $filter->toQuery(), 'Order');
    }

     public static function getBulkprice($id)
    {
       return self::getCollection('/products/' . $id . '/discount_rules');
    }
    /**
     * The number of orders in the collection.
     *
     * @param array $filter
     * @return int
     */
    public static function getOrdersCount()
    {
        return self::getCount('/orders/count');
    }

    /**
     * The order count grouped by order status
     *
     * @param array $filter
     * @return Resources\OrderStatus
     */
    public static function getOrderStatusesWithCounts($filter = array())
    {
        $filter = Filter::create($filter);
        $resource = self::getResource('/orders/count' . $filter->toQuery(), "OrderStatus");
        return $resource->statuses;
    }

    /**
     * A single order.
     *
     * @param int $id order id
     * @return Resources\Order
     */
    public static function getOrder($id)
    {
        return self::getResource('/orders/' . $id, 'Order');
    }

    /**
     * @param $orderID
     * @return mixed
     */
    public static function getOrderProducts($orderID)
    {
        return self::getCollection('/orders/' . $orderID . '/products', 'OrderProduct');
    }

    /**
     * The total number of order products in the collection.
     *
     * @param $orderID
     * @param array $filter
     * @return mixed
     */
    public static function getOrderProductsCount($orderID, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/orders/' . $orderID . '/products/count' . $filter->toQuery());
    }

    /**
     * Delete the given order (unlike in the Control Panel, this will permanently
     * delete the order).
     *
     * @param int $id order id
     * @return mixed
     */
    public static function deleteOrder()
    {
        return self::deleteResource('/orders');
    }

    /**
     * Delete all orders.
     *
     * @return mixed
     */
    public static function deleteAllOrders()
    {
        return self::deleteResource('/orders');
    }

    /**
     * Create an order
     *
     * @param $object
     * @return mixed
     */
    public static function createOrder($object)
    {
        return self::createResource('/orders', $object);
    }

    /**
     * Update the given order.
     *
     * @param int $id order id
     * @param mixed $object fields to update
     * @return mixed
     */
    public static function updateOrder($id, $object)
    {
        return self::updateResource('/orders/' . $id, $object);
    }

    /**
     * The list of customers.
     *
     * @param array $filter
     * @return array
     */
    public static function getCustomers($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/customers' . $filter->toQuery(), 'Customer');
    }

    /**
     * The total number of customers in the collection.
     *
     * @param array $filter
     * @return int
     */
    public static function getCustomersCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/customers/count' . $filter->toQuery());
    }

     public static function getProductsSkusCount($id)
   {
       return self::getCount('/products/' . $id .'/skus/count');
   }
    
    /**
     * Bulk delete customers.
     *
     * @param array $filter
     * @return array
     */
    public static function deleteCustomers($filter = array())
    {
        $filter = Filter::create($filter);
        return self::deleteResource('/customers' . $filter->toQuery());
    }

    /**
     * A single customer by given id.
     *
     * @param int $id customer id
     * @return Resources\Customer
     */
    public static function getCustomer($id)
    {
        return self::getResource('/customers/' . $id, 'Customer');
    }
    
    public static function getCustomerGroup($id)
    {
        return self::getResource('/customer_groups/' . $id, 'CustomerGroups');
    }

    
    public static function getOrderCoupon($orderID)
    {
        return self::getResource('/orders/' . $orderID . '/coupons');
    }


    /**
     * Create a new customer from the given data.
     *
     * @param mixed $object
     * @return mixed
     */
    public static function createCustomer($object)
    {
        return self::createResource('/customers', $object);
    }
	
	 public static function createCustomeraddress($customer_id,$object)
    {
        return self::createResource('/customers/'.$customer_id.'/addresses', $object);
    }

    /**
     * Update the given customer.
     *
     * @param int $id customer id
     * @param mixed $object
     * @return mixed
     */
    public static function updateCustomer($id, $object)
    {
        return self::updateResource('/customers/' . $id, $object);
    }

    /**
     * Delete the given customer.
     *
     * @param int $id customer id
     * @return mixed
     */
    public static function deleteCustomer($id)
    {
        return self::deleteResource('/customers/' . $id);
    }

    /**
     * A list of addresses belonging to the given customer.
     *
     * @param int $id customer id
     * @return array
     */
    public static function getCustomerAddresses($id)
    {
        return self::getCollection('/customers/' . $id . '/addresses', 'Address');
    }

    /**
     * Returns the collection of option sets.
     *
     * @param array $filter
     * @return array
     */
    public static function getOptionSets($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/optionsets' . $filter->toQuery(), 'OptionSet');
    }

    /**
     * Create Optionsets
     *
     * @param $object
     * @return mixed
     */
    public static function createOptionSet($object)
    {
        return self::createResource('/optionsets', $object);
    }

    /**
     * Create Option Set Options
     *
     * @param $object
     * @param $id
     * @return mixed
     */
    public static function createOptionSetOption($object, $id)
    {
        return self::createResource('/optionsets/' . $id . '/options', $object);
    }

    /**
     * Returns the total number of option sets in the collection.
     *
     * @return int
     */
    public static function getOptionSetsCount()
    {
        return self::getCount('/optionsets/count');
    }
    
    public static function getCustomerCount()
    {
        return self::getCount('/customers/count');
    }

    /**
     * A single option set by given id.
     *
     * @param int $id option set id
     * @return Resources\OptionSet
     */
    public static function getOptionSet($id)
    {
        return self::getResource('/optionsets/' . $id, 'OptionSet');
    }

    /**
     * Update the given option set.
     *
     * @param int $id option set id
     * @param mixed $object
     * @return mixed
     */
    public static function updateOptionSet($id, $object)
    {
        return self::updateResource('/optionsets/' . $id, $object);
    }

    /**
     * Delete the given option set.
     *
     * @param int $id option id
     * @return mixed
     */
    public static function deleteOptionSet($id)
    {
        Client::deleteResource('/optionsets/' . $id);
    }

    /**
     * Status code used to represent the state of an order.
     *
     * @param int $id order status id
     *
     * @return mixed
     */
    public static function getOrderStatus($id)
    {
        return self::getResource('/order_statuses/' . $id, 'OrderStatus');
    }

    /**
     * Status codes used to represent the state of an order.
     *
     * @return array
     */
    public static function getOrderStatuses()
    {
        return self::getCollection('/order_statuses', 'OrderStatus');
    }

    /**
     * Get collection of product skus
     *
     * @param array $filter
     * @return mixed
     */
    public static function getSkus($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/products/skus' . $filter->toQuery(), 'Sku');
    }
    
    
    public static function getProductsSkus($id)
    {
        return self::getCollection('/products/' . $id .'/skus');
    }

    /**
     * Create sku
     *
     * @param $object
     * @return mixed
     */
    public static function createSku($object)
    {
        return self::createResource('/product/skus', $object);
    }

    /**
     * Update sku
     *
     * @param $id
     * @param $object
     * @return mixed
     */
    public static function updateSku($productid,$id, $object)
    {
        return self::updateResource('/products/' . $productid .'/skus/'. $id, $object);
    }

    /**
     * Returns the total number of SKUs in the collection.
     *
     * @return int
     */
    public static function getSkusCount()
    {
        return self::getCount('/products/skus/count');
    }

    /**
     * Get a single coupon by given id.
     *
     * @param int $id customer id
     * @return Resources\Coupon
     */
    public static function getCoupon($id)
    {
        return self::getResource('/coupons/' . $id, 'Coupon');
    }

    /**
     * Get coupons
     *
     * @param array $filter
     * @return mixed
     */
    public static function getCoupons($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/coupons' . $filter->toQuery(), 'Coupon');
    }
	
	
	 public static function getCustomerGroups($filter = array())
    {
        return self::getCollection('/customer_groups');
    }

    /**
     * Create coupon
     *
     * @param $object
     * @return mixed
     */
    public static function createCoupon($object)
    {
        return self::createResource('/coupons', $object);
    }

    /**
     * Update coupon
     *
     * @param $id
     * @param $object
     * @return mixed
     */
    public static function updateCoupon($id, $object)
    {
        return self::updateResource('/coupons/' . $id, $object);
    }

    /**
     * Delete the given coupon.
     *
     * @param int $id coupon id
     * @return mixed
     */
    public static function deleteCoupon($id)
    {
        return self::deleteResource('/coupons/' . $id);
    }

    /**
     * Delete all Coupons.
     *
     * @return mixed
     */
    public static function deleteAllCoupons()
    {
        return self::deleteResource('/coupons');
    }

    /**
     * Return the number of coupons
     *
     * @return int
     */
    public static function getCouponsCount()
    {
        return self::getCount('/coupons/count');
    }

    /**
     * The request logs with usage history statistics.
     */
    public static function getRequestLogs()
    {
        return self::getCollection('/requestlogs', 'RequestLog');
    }

    public static function getStore()
    {
        $response = self::connection()->get(self::$api_path . '/store');
        return $response;
    }

    /**
     * The number of requests remaining at the current time. Based on the
     * last request that was fetched within the current script. If no
     * requests have been made, pings the time endpoint to get the value.
     *
     * @return int
     */
    public static function getRequestsRemaining()
    {
        $limit = self::connection()->getHeader('X-BC-ApiLimit-Remaining');

        if (!$limit) {
            $result = self::getTime();

            if (!$result) {
                return false;
            }

            $limit = self::connection()->getHeader('X-BC-ApiLimit-Remaining');
        }

        return (int)$limit;
    }

    /**
     * Get a single shipment by given id.
     *
     * @param $orderID
     * @param $shipmentID
     * @return mixed
     */
    public static function getShipment($orderID, $shipmentID)
    {
        return self::getResource('/orders/' . $orderID . '/shipments/' . $shipmentID, 'Shipment');
    }

    /**
     * Get shipments for a given order
     *
     * @param $orderID
     * @param array $filter
     * @return mixed
     */
    public static function getShipments($orderID, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders/' . $orderID . '/shipments' . $filter->toQuery(), 'Shipment');
    }

    /**
     * Create shipment
     *
     * @param $orderID
     * @param $object
     * @return mixed
     */
    public static function createShipment($orderID, $object)
    {
        return self::createResource('/orders/' . $orderID . '/shipments', $object);
    }

    /**
     * Update shipment
     *
     * @param $orderID
     * @param $shipmentID
     * @param $object
     * @return mixed
     */
    public static function updateShipment($orderID, $shipmentID, $object)
    {
        return self::updateResource('/orders/' . $orderID . '/shipments/' . $shipmentID, $object);
    }

    /**
     * Delete the given shipment.
     *
     * @param $orderID
     * @param $shipmentID
     * @return mixed
     */
    public static function deleteShipment($orderID, $shipmentID)
    {
        return self::deleteResource('/orders/' . $orderID . '/shipments/' . $shipmentID);
    }

    /**
     * Delete all Shipments for the given order.
     *
     * @param $orderID
     * @return mixed
     */
    public static function deleteAllShipmentsForOrder($orderID)
    {
        return self::deleteResource('/orders/' . $orderID . '/shipments');
    }

    /**
     * Get a single order shipping address by given order and order shipping address id.
     *
     * @param $orderID
     * @param $orderShippingAddressID
     * @return mixed
     */
    public static function getOrderShippingAddress($orderID, $orderShippingAddressID)
    {
        return self::getResource('/orders/' . $orderID . '/shipping_addresses/' . $orderShippingAddressID, 'Address');
    }

    /**
     * Get order shipping addresses for a given order
     *
     * @param $orderID
     * @param array $filter
     * @return mixed
     */
    public static function getOrderShippingAddresses($orderID, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders/' . $orderID . '/shipping_addresses' . $filter->toQuery(), 'Address');
    }

    /**
     * Create a new currency.
     *
     * @param mixed $object fields to create
     * @return mixed
     */
    public static function createCurrency($object)
    {
        return self::createResource('/currencies', $object);
    }

    /**
     * Returns a single currency resource by the given id.
     *
     * @param int $id currency id
     * @return Resources\Currency|string
     */
    public static function getCurrency($id)
    {
        return self::getResource('/currencies/' . $id, 'Currency');
    }

    /**
     * Update the given currency.
     *
     * @param int $id currency id
     * @param mixed $object fields to update
     * @return mixed
     */
    public static function updateCurrency($id, $object)
    {
        return self::updateResource('/currencies/' . $id, $object);
    }

    /**
     * Delete the given currency.
     *
     * @param int $id currency id
     * @return mixed
     */
    public static function deleteCurrency($id)
    {
        return self::deleteResource('/currencies/' . $id);
    }

    /**
     * Returns the default collection of currencies.
     *
     * @param array $filter
     * @return mixed array|string list of currencies or XML string if useXml is true
     */
    public static function getCurrencies($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/currencies' . $filter->toQuery(), 'Currency');
    }

    /**
     * Returns the total number of currencies in the collection.
     *
     * @param array $filter
     * @return int|string number of currencies or XML string if useXml is true
     */
    public static function getCurrenciesCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/currencies/count' . $filter->toQuery());
    }

    /**
     * Create a new product image.
     *
     * @param string $productId
     * @param mixed $object
     * @return mixed
     */
    public static function createProductImage($productId, $object)
    {
        return self::createResource('/products/' . $productId . '/images', $object);
    }

    /**
     * Update a product image.
     *
     * @param string $productId
     * @param string $imageId
     * @param mixed $object
     * @return mixed
     */
    public static function updateProductImage($productId, $imageId, $object)
    {
        return self::updateResource('/products/' . $productId . '/images/' . $imageId, $object);
    }

    /**
     * Returns a product image resource by the given product id.
     *
     * @param int $productId
     * @param int $imageId
     * @return Resources\ProductImage|string
     */
    public static function getProductImage($productId, $imageId)
    {
        return self::getResource('/products/' . $productId . '/images/' . $imageId, 'ProductImage');
    }

    /**
     * Delete the given product image.
     *
     * @param int $productId
     * @param int $imageId
     * @return mixed
     */
    public static function deleteProductImage($productId, $imageId)
    {
        return self::deleteResource('/products/' . $productId . '/images/' . $imageId);
    }
	
	public static function deleteProductImages($productId)
	{
		return self::deleteResource('/products/' . $productId . '/images');
	}
	
	// pages  	
	public static function getPagesCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/pages' . $filter->toQuery(), 'Page');
    }
	
		
}
