<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'crossselling/crossselling.php';

class CrossSellingOverride extends CrossSelling
{
    /**
     * @param array $products_id an array of product ids
     * @return array
     */
    protected function getOrderProducts(array $products_id)
    {
        $q_orders = 'SELECT o.id_order
        FROM '._DB_PREFIX_.'orders o
        LEFT JOIN '._DB_PREFIX_.'order_detail od ON (od.id_order = o.id_order)
        WHERE o.valid = 1 AND od.product_id IN ('.implode(',', $products_id).')';
        $orders = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($q_orders);

        $final_products_list = array();

        if (count($orders) > 0) {
            $list = '';
            foreach ($orders as $order) {
                $list .= (int)$order['id_order'].',';
            }
            $list = rtrim($list, ',');

            $list_product_ids = join(',', $products_id);

            if (Group::isFeatureActive()) {
                $sql_groups_join = '
                LEFT JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.`id_category` = product_shop.id_category_default
                    AND cp.id_product = product_shop.id_product)
                LEFT JOIN `'._DB_PREFIX_.'category_group` cg ON (cp.`id_category` = cg.`id_category`)';
                $groups = FrontController::getCurrentCustomerGroups();
                $sql_groups_where = 'AND cg.`id_group` '.(count($groups) ? 'IN ('.implode(',', $groups).')' : '='.(int)Group::getCurrent()->id);
            }

            $order_products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT DISTINCT od.product_id, pl.name, pl.description_short, pl.link_rewrite, p.reference, i.id_image, product_shop.show_price,
                    cl.link_rewrite category, p.ean13, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity
                FROM '._DB_PREFIX_.'order_detail od
                LEFT JOIN '._DB_PREFIX_.'product p ON (p.id_product = od.product_id)
                '.Shop::addSqlAssociation('product', 'p').
                (Combination::isFeatureActive() ? 'LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
                ON (p.`id_product` = pa.`id_product`)
                '.Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.`default_on` = 1').'
                '.Product::sqlStock('p', 'product_attribute_shop', false, $this->context->shop) :  Product::sqlStock('p', 'product', false,
                    $this->context->shop)).'
                LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (pl.id_product = od.product_id'.Shop::addSqlRestrictionOnLang('pl').')
                LEFT JOIN '._DB_PREFIX_.'category_lang cl ON (cl.id_category = product_shop.id_category_default'
                    .Shop::addSqlRestrictionOnLang('cl').')
                LEFT JOIN '._DB_PREFIX_.'image i ON (i.id_product = od.product_id)
                '.(Group::isFeatureActive() ? $sql_groups_join : '').'
                WHERE od.id_order IN ('.$list.')
                AND pl.id_lang = '.(int)$this->context->language->id.'
                AND cl.id_lang = '.(int)$this->context->language->id.'
                AND od.product_id NOT IN ('.$list_product_ids.')
                AND i.cover = 1
                AND product_shop.active = 1
                '.(Group::isFeatureActive() ? $sql_groups_where : '').'
                ORDER BY RAND()
                LIMIT '.(int)Configuration::get('CROSSSELLING_NBR'));

            $tax_calc = Product::getTaxCalculationMethod();

            foreach ($order_products as &$order_product) {
                $order_product['id_product'] = (int)$order_product['product_id'];
                $order_product['image'] = $this->context->link->getImageLink($order_product['link_rewrite'],
                    (int)$order_product['product_id'].'-'.(int)$order_product['id_image'], ImageType::getFormatedName('home'));
                $order_product['link'] = $this->context->link->getProductLink((int)$order_product['product_id'], $order_product['link_rewrite'],
                    $order_product['category'], $order_product['ean13']);
                if (Configuration::get('CROSSSELLING_DISPLAY_PRICE') && ($tax_calc == 0 || $tax_calc == 2)) {
                    $order_product['displayed_price'] = Product::getPriceStatic((int)$order_product['product_id'], true, null);
                } elseif (Configuration::get('CROSSSELLING_DISPLAY_PRICE') && $tax_calc == 1) {
                    $order_product['displayed_price'] = Product::getPriceStatic((int)$order_product['product_id'], false, null);
                }
                $order_product['allow_oosp'] = Product::isAvailableWhenOutOfStock((int)$order_product['out_of_stock']);

                if (!isset($final_products_list[$order_product['product_id'].'-'.$order_product['id_image']])) {
                    $final_products_list[$order_product['product_id'].'-'.$order_product['id_image']] = $order_product;
                }
            }
        }

        return $final_products_list;
    }
}