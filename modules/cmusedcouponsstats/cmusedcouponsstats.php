<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class CmUsedCouponsStats extends ModuleGrid
{
    private $html;
	private $query;
	private $columns;
	private $default_sort_column;
	private $default_sort_direction;
	private $empty_message;
	private $paging_message;

    public function __construct()
    {
        $this->name = 'cmusedcouponsstats';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Michal Danko';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Custom Module - Statistics of Used Coupons in Orders');
        $this->description = $this->l('Displays statistics on coupon usage in orders.');
        $this->default_sort_column = 'orderDate';
		$this->default_sort_direction = 'DESC';
		$this->empty_message = $this->l('Empty recordset returned.');
		$this->paging_message = sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}');

		$this->columns = [
			[
				'id' => 'orderId',
				'header' => $this->l('Order ID'),
				'dataIndex' => 'id_order',
				'align' => 'center',
			],
			[
				'id' => 'couponId',
				'header' => $this->l('Coupon ID'),
				'dataIndex' => 'couponId',
				'align' => 'center',
			],
			[
				'id' => 'orderTotalWithoutVat',
				'header' => $this->l('Order Total Without VAT'),
				'dataIndex' => 'orderTotalWithoutVat',
				'align' => 'center',
			],
			[
				'id' => 'customerFirstName',
				'header' => $this->l('Customer First Name'),
				'dataIndex' => 'customerFirstName',
				'align' => 'left',
			],
			[
				'id' => 'customerLastName',
				'header' => $this->l('Customer Last Name'),
				'dataIndex' => 'customerLastName',
				'align' => 'left',
			],
			[
				'id' => 'orderDate',
				'header' => $this->l('Order Date'),
				'dataIndex' => 'orderDate',
				'align' => 'center'
			],
		];
    }

    public function install()
    {
		return (parent::install() && $this->registerHook('AdminStatsModules'));
    }

    public function hookAdminStatsModules()
    {
        $engine_params = array(
			'id' => 'id_product',
			'title' => $this->displayName,
			'columns' => $this->columns,
			'defaultSortColumn' => $this->default_sort_column,
			'defaultSortDirection' => $this->default_sort_direction,
			'emptyMessage' => $this->empty_message,
			'pagingMessage' => $this->paging_message
		);

		if (Tools::getValue('export')) {
			$this->csvExport($engine_params);
		}

		$this->html = '
			<div class="panel-heading">
				'.$this->displayName.'
			</div>
			'.$this->engine($engine_params).'
			<a class="btn btn-default export-csv" href="'.Tools::safeOutput($_SERVER['REQUEST_URI'].'&export=1').'">
				<i class="icon-cloud-upload"></i> '.$this->l('CSV Export').'
			</a>';

		return $this->html;
    }

	public function getData()
	{
		$currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

		$this->query = 'SELECT o.id_order, o.total_paid_tax_excl AS orderTotalWithoutVat, cr.id_cart_rule AS couponId, c.firstname AS customerFirstName, c.lastname AS customerLastName, o.date_add AS orderDate FROM ' . _DB_PREFIX_ . 'orders o
			INNER JOIN ' . _DB_PREFIX_ . 'order_cart_rule ocr ON o.id_order = ocr.id_order
			INNER JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON ocr.id_cart_rule = cr.id_cart_rule
			INNER JOIN ' . _DB_PREFIX_ . 'customer c ON o.id_customer = c.id_customer
			ORDER BY o.date_add DESC';

		if (($this->_start === 0 || Validate::IsUnsignedInt($this->_start)) && Validate::IsUnsignedInt($this->_limit)) {
			$this->query .= ' LIMIT '.(int)$this->_start.', '.(int)$this->_limit;
		}

		$values = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query);

		foreach ($values as &$value)
		{
			$value['orderTotalWithoutVat'] = Tools::displayPrice($value['orderTotalWithoutVat'], $currency);
		}
		unset($value);

		$this->_values = $values;
		$this->_totalCount = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT FOUND_ROWS()');
	}
}
