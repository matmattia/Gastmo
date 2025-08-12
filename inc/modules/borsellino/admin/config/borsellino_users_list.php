<?php
if (isset($_REQUEST['calc_totals'])) {
	$borsellino = new \Borsellino\BorsellinoView();
	echo json_encode(array('ok' => 1, 'html' => printHtmlTag(
		'dl',
		printHtmlTag('dt', 'Totale corrente').printHtmlTag('dd', printMoney($borsellino->getUserTotal($_REQUEST['calc_totals'])))
		.printHtmlTag('dt', 'Totale previsto').printHtmlTag('dd', printMoney($borsellino->getUserExpectedTotal($_REQUEST['calc_totals'])))
	)));
	unset($borsellino);
	exit();
}

if (isset($_REQUEST['update_totals'])) {
	$objUserArchivedOrdersTotals = new \Borsellino\BorsellinoUserArchivedOrdersTotals();
	echo json_encode(array('ok' => $objUserArchivedOrdersTotals->checkUser($_REQUEST['update_totals'], true) ? 1 : 0));
	exit();
}

$object = '\Borsellino\BorsellinoUserView';
$sql = array('order' => array('alias' => 'ASC'));
$operations = array(
	'update_total' => array(
		'href' => '#',
		'onclick' => 'borsellino.updateTotals({URLENCODE|ID});return false;',
		'title' => 'Aggiorna i totali',
		'class_icon' => 'refresh'
	),
	'calc_expected_total' => array(
		'href' => '#',
		'onclick' => 'borsellino.calcTotals({URLENCODE|ID});return false;',
		'title' => 'Calcola i totali',
		'class_icon' => 'calculator'
	),
	'view' => array(
		'href' => _ADMINH.'index.php?page=list_borsellino&search_user={URLENCODE|FIELD0}',
		'title' => 'Movimenti',
		'class_icon' => 'search'
	)
);
$fields = array(
	array('field' => 'alias', 'title' => 'Utente'),
	array('field' => 'total', 'title' => 'Totale', 'print_function' => function($v) {
		return number_format(is_numeric($v) ? floatval($v) : 0, 2, ',', '.');
	})
);

ob_start();
?>
<script type="text/javascript">
var borsellino = {
	'calcTotals': function(id) {
		modal.setTitle('Totali');
		modal.openLoading();
		admin.ajaxOperation({
			'url': _ROOT + 'index.php?page=list_borsellino_users',
			'data': {
				'calc_totals': id
			}
		}, {
			'onSuccess': function(d) {
				modal.setContent(d.html);
			},
			'onError': function() {
				modal.close();
			}
		});
	},
	'updateTotals': function(id) {
		admin.ajaxOperation({
			'url': _ROOT + 'index.php?page=list_borsellino_users',
			'data': {
				'update_totals': id
			}
		}, {
			'onSuccess': function(d) {
				window.location.reload();
			}
		});
	}
};
</script>
<?php
$page->setHeadInclude(ob_get_contents());
ob_end_clean();