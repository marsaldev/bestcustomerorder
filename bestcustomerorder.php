<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 */

declare(strict_types=1);

class BestCustomerOrder extends Module
{
    protected $_totalMoneySpent;

    public function __construct()
    {
        $this->name = 'bestcustomerorder';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = ['min' => '1.7.7', 'max' => _PS_VERSION_];
        $this->author = 'PrestaShop';

        parent::__construct();

        $this->displayName = 'Best Customer in Order Page';
        $this->description = 'Show in the order page (BO) if the order has been made by a Best Customer';

        $this->_totalMoneySpent = 150;
    }

    public function install()
    {
        return parent::install() and
            $this->registerHook('displayAdminOrderTop') and
            $this->registerHook('displayAdminOrderSide');
    }

    /**
     * Displays best customer alert
     */
    public function hookDisplayAdminOrderTop(array $params)
    {
        /** @var Order $order */
        $order = new Order((int)$params['id_order']);
        /** @var Customer $customer */
        $customer = $order->getCustomer();

        if($this->isBestCustomer($customer->id)) {
            return $this->render($this->getModuleTemplatePath() . 'bestcustomer.html.twig', [
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname
            ]);
        }
    }

    public function isBestCustomer($id_customer)
    {
        $this->query = '
		SELECT SQL_CALC_FOUND_ROWS c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`,
			COUNT(co.`id_connections`) as totalVisits,
			IFNULL((
				SELECT ROUND(SUM(IFNULL(op.`amount`, 0) / cu.conversion_rate), 2)
				FROM `'._DB_PREFIX_.'orders` o
				LEFT JOIN `'._DB_PREFIX_.'order_payment` op ON o.reference = op.order_reference
				LEFT JOIN `'._DB_PREFIX_.'currency` cu ON o.id_currency = cu.id_currency
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN '.$this->getDate().'
				AND o.valid
			), 0) as totalMoneySpent,
			IFNULL((
				SELECT COUNT(*)
				FROM `'._DB_PREFIX_.'orders` o
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN '.$this->getDate().'
				AND o.valid
			), 0) as totalValidOrders
		FROM `'._DB_PREFIX_.'customer` c
		LEFT JOIN `'._DB_PREFIX_.'guest` g ON c.`id_customer` = g.`id_customer`
		LEFT JOIN `'._DB_PREFIX_.'connections` co ON g.`id_guest` = co.`id_guest`
		WHERE c.`id_customer` = '.(int)$id_customer.' AND
		co.date_add BETWEEN '.$this->getDate()
            .Shop::addSqlRestriction(Shop::SHARE_CUSTOMER, 'c').
            ' GROUP BY c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`';

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($this->query);

        if($result and $result['totalMoneySpent'] >= $this->_totalMoneySpent)
            return true;

        return false;
    }

    public function getDate()
    {
        return ModuleGraph::getDateBetween($this->context->employee->id);
    }

    /**
     * Render a twig template.
     */
    private function render(string $template, array $params = []): string
    {
        /** @var Twig_Environment $twig */
        $twig = $this->get('twig');

        return $twig->render($template, $params);
    }

    /**
     * Get path to this module's template directory
     */
    private function getModuleTemplatePath(): string
    {
        return sprintf('@Modules/%s/views/templates/admin/', $this->name);
    }
}
