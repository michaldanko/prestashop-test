<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class CmStockNotifications extends Module
{
	public function __construct()
    {
        $this->name = 'cmstocknotifications';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Michal Danko';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Custom Module - Stock Notifications');
        $this->description = $this->l('Generates a CSV file and sends an email when a product\'s stock reaches zero.');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            Configuration::updateValue('CM_STOCK_NOTIFICATIONS_EMAIL', 'kristin@abc-zoo.sk');
    }

    public function uninstall()
    {
        return parent::uninstall() && Configuration::deleteByName('CM_STOCK_NOTIFICATIONS_EMAIL');
    }

	/**
	 * Display the module settings form.
	 */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitStockAlert')) {
            $email = Tools::getValue('CM_STOCK_NOTIFICATIONS_EMAIL');

            if (!Validate::isEmail($email)) {
                $output .= $this->displayError($this->l('Invalid email address.'));
            } else {
                Configuration::updateValue('CM_STOCK_NOTIFICATIONS_EMAIL', $email);
                $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
            }
        }

        return $output . $this->renderForm();
    }

	/**
	 * Render the module settings form.
	 */
    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => ['title' => $this->l('Settings')],
                'input' => [[
                    'type' => 'text',
                    'label' => $this->l('Recipient Email'),
                    'name' => 'CM_STOCK_NOTIFICATIONS_EMAIL',
                    'size' => 50,
                    'required' => true
                ]],
                'submit' => ['title' => $this->l('Save')]
            ]
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitStockAlert';
        $helper->fields_value['CM_STOCK_NOTIFICATIONS_EMAIL'] = Configuration::get('CM_STOCK_NOTIFICATIONS_EMAIL');

        return $helper->generateForm([$fields_form]);
    }

	/**
	 * When an order status is edited, check if any product has reached 0 stock.
	 *
	 * @param array $params
	 */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $order = new Order((int)$params['id_order']);
        $this->checkStockQty($order);
    }

	/**
	 * Check if any product has reached 0 stock.
	 *
	 * @param Order $order
	 */
	protected function checkStockQty($order)
	{
        foreach ($order->getProducts() as $product) {
            $id_product = (int)$product['product_id'];
            $id_product_attribute = (int)$product['product_attribute_id']; // Variant ID (if applicable)

            // Get available stock for this specific product or combination
            $quantity_available = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);

            if ($quantity_available == 0) {
                $this->sendEmail($product);
				$this->logEvent($product);
            }
        }
	}

	/**
	 * Get the total sales of a product in the last 30 days.
	 *
	 * @param int $productId
	 */
    protected function getSalesLast30Days($productId)
    {
        $sql = 'SELECT SUM(od.product_quantity) FROM ' . _DB_PREFIX_ . 'order_detail od
                JOIN ' . _DB_PREFIX_ . 'orders o ON od.id_order = o.id_order
                WHERE od.product_id = ' . (int)$productId . ' AND o.date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)';

        return (int)Db::getInstance()->getValue($sql);
    }

	/**
	 * Send an email with a CSV file containing the product information.
	 *
	 * @param array $product
	 */
	protected function sendEmail($product)
    {
        $email = Configuration::get('CM_STOCK_NOTIFICATIONS_EMAIL');
        $sales = $this->getSalesLast30Days($product['product_id']);
		$tmpDir = _PS_MODULE_DIR_ . $this->name . '/tmp/';

		if (!file_exists($tmpDir)) {
			mkdir($tmpDir, 0777, true);
		}

		// Create CSV file with the product information
		$csvFilePath = $tmpDir . 'stock_notifications_' . $product['product_id'] . '_' . time() . '.csv';
		$csvFile = fopen($csvFilePath, 'w');
        fputcsv($csvFile, ['Product Name', 'EAN', 'Supplier Code', 'Sold Last 30 Days']);
		fputcsv($csvFile, [$product['product_name'], $product['product_ean13'], $product['supplier_reference'], $sales]);
        fclose($csvFile);

		// Prepare attachment
        $attachment = [
            'content' => file_get_contents($csvFilePath),
            'name' => basename($csvFilePath),
            'mime' => 'text/csv',
        ];

		// Send email with the CSV file attached
        Mail::Send(
            (int)Configuration::get('PS_LANG_DEFAULT'),
            'cm_stock_notifications',
            'Skladová zásoba klesla na 0 – ' . $product['product_name'],
            [
				'{product_name}' => $product['product_name'],
			],
            $email,
            null,
            null,
            null,
            $attachment,
            null,
            _PS_MODULE_DIR_ . $this->name . '/mails/'
        );

		// Delete the CSV file.
        unlink($csvFilePath);
    }

	/**
	 * Log the event to a file.
	 *
	 * @param array $product
	 */
    protected function logEvent($product)
	{
		$logDir = _PS_MODULE_DIR_ . $this->name . '/logs/';

		if (!file_exists($logDir)) {
			mkdir($logDir, 0777, true);
		}

		$logMessage = '[' . date('Y-m-d H:i:s') . '] Stock reached 0 for product ID: ' . $product['product_id'] . ' (' . $product['product_name'] . ")\n";

		file_put_contents($logDir . 'stock_notifications.log', $logMessage, FILE_APPEND);
	}
}
