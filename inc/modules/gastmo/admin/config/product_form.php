<?php
namespace Gastmo;

if (isset($_REQUEST['get_categories_by_order'])) {
	echo json_encode(array('ok' => 1, 'options' => getCategoriesOptionsByOrder($_REQUEST['get_categories_by_order'])));
	exit();
}

$object = '\Gastmo\Product';

$order = null;
$objOrder = new Order();
if ($edit && isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && $_REQUEST['id'] > 0) {
	$order = \DB::queryOne($objOrder->getSql(array(
		'select' => array('orders.id'),
		'join' => array(
			'categories' => array('categories.order', 'orders.id'),
			'products' => array('products.category', 'categories.id')
		),
		'where' => array(
			array('field' => 'products.id', 'value' => $_REQUEST['id'])
		),
		'limit' => 1
	)));
}
$orders = array_merge(array(''), $objOrder->getList(array(
	'select' => array(
		array('value' => 'id', 'as' => 'value'),
		array('value' => 'title', 'as' => 'label')
	),
	'where' => array(array('field' => 'id', 'match' => 'IN', 'value' => Order::getUserManagedOrders(null, 'id'))),
	'order' => array('closing_date' => 'DESC')
)));
unset($objOrder);

$objProductType = new ProductType();

$fields = array(
	array('field' => 'title', 'title' => 'Nome'),
	array('field' => 'order', 'title' => 'Ordine', 'type' => 'select', 'value' => $orders, 'selected' => $order),
	array('field' => 'category', 'title' => 'Categoria', 'type' => 'select', 'value' => getCategoriesOptionsByOrder($order)),
	array('field' => 'qty_package', 'title' => 'Quantità Collo', 'type' => 'number', 'attributes' => array('step' => 0.001)),
	array('field' => 'um', 'title' => 'Unità di Misura'),
	array('field' => 'price', 'title' => 'Prezzo', 'type' => 'number', 'attributes' => array('step' => 0.001)),
	array('field' => 'maker', 'title' => 'Produttore'),
	array('field' => 'location', 'title' => 'Località'),
	array('field' => 'vat', 'title' => 'IVA', 'type' => 'number', 'attributes' => array('step' => 'any')),
	array('field' => 'package_price', 'title' => 'Prezzo Collo', 'type' => 'number', 'attributes' => array('step' => 0.001)),
	array('field' => 'note', 'title' => 'Note', 'type' => 'textarea'),
	array('field' => 'type', 'title' => 'Tipo', 'type' => 'select', 'value' => array_merge(
		array(array('value' => 0, 'label' => '')),
		$objProductType->getList(array(
		'select' => array(
			array('value' => 'id', 'as' => 'value'),
			array('value' => 'title', 'as' => 'label')
		)
		))
	)),
	array('field' => 'pos', 'title' => 'Posizione', 'type' => 'number')
);
unset($orders, $order, $categories, $objProductType);

ob_start();
?>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
	var inp_order = document.querySelector('select[name="order"]'),
		inps_price = document.querySelectorAll('input[name="price"],input[name="qty_package"]'), l = inps_price.length, i = 0;
	if (inp_order) {
		inp_order.addEventListener('change', function() {
			var f = this.closest('form'), c = f ? f.querySelector('select[name="category"]') : null;
			if (c) {
				admin_form.updateSelectOptionsAjax(c, {
					'url': _ROOT + 'index.php?page=create_product',
					'data': {
						'get_categories_by_order': this.value
					}
				});
			}
		});
	}
	for (i = 0; i < l; i++) {
		inps_price[i].addEventListener('change', function() {
			var f = this.closest('form'), inp_price = null, inp_qty = null, inp_price_tot = null;
			if (f) {
				inp_price = f.querySelector('input[name="price"]');
				inp_qty = f.querySelector('input[name="qty_package"]');
				inp_price_tot = f.querySelector('input[name="package_price"]');
				if (inp_price && inp_qty && inp_price_tot) {
					inp_price_tot.value = (isNaN(inp_price.value) ? 0 : inp_price.value) * (isNaN(inp_qty.value) ? 0 : inp_qty.value);
				}
			}
		});
	}
});
</script>
<?php
$page->setHeadInclude(ob_get_contents());
ob_end_clean();

/**
 * Restituisce le di un ordine come opzioni
 * @param integer $order ID dell'ordine
 * @return array
 */
function getCategoriesOptionsByOrder($order = null) {
	$categories = array('');
	if (is_numeric($order) && $order > 0) {
		$objCategory = new Category();
		$categories = array_merge($categories, $objCategory->getList(array(
			'select' => array(
				array('value' => 'id', 'as' => 'value'),
				array('value' => 'title', 'as' => 'label')
			),
			'where' => array(array('field' => 'categories.order', 'value' => $order)),
			'order' => array('title' => 'ASC')
		)));
		unset($objCategory);
	}
	return $categories;
}