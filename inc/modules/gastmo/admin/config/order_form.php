<?php
namespace Gastmo;

if (isset($_GET['download_export'])) {
	$order = new OrderExporter(isset($_GET['id']) ? $_GET['id'] : null);
	$order->downloadExport($_GET['download_export'], isset($_GET['date']) ? $_GET['date'] : null);
}
if (isset($_POST['refresh_export'])) {
	$order = new OrderExporter(isset($_POST['id']) ? $_POST['id'] : null);
	echo json_encode(array('ok' => $order->refreshExport($_POST['refresh_export']) ? 1 : 0));
	unset($order);
	exit();
}
if (isset($_POST['delete_export'])) {
	$order = new OrderExporter(isset($_POST['id']) ? $_POST['id'] : null);
	echo json_encode(array('ok' => $order->deleteExport($_POST['delete_export']) ? 1 : 0));
	unset($order);
	exit();
}
$u = \User::getLoggedUserObject(true);
if ($u->get('level') == 'magazziniere' || isset($_GET['import_qty']) || isset($_GET['import_votes'])) {
	if (isset($_GET['import_votes'])) {
		$use_namespace = '\Gastmo';
		function edit_order($data, $files = array()) {
			$res = false;
			if (is_array($files) && isset($files['csv'])) {
				$order = new OrderExporter($_GET['import_votes']);
				$res = $order->importVotes($files['csv'], $data);
				unset($order);
			}
			return $res;
		}
		$order = new Order($_GET['import_votes']);
		if ($order->exists()) {
			$data = $order->getData();
			$fields = array(
				array('field' => 'title', 'title' => 'Titolo', 'type' => 'print'),
				array('field' => 'csv', 'title' => 'CSV dei prodotti', 'type' => 'file', 'check' => array('type' => '*.csv')),
				array('field' => 'csv_delimiter', 'title' => 'Separatore campi CSV', 'value' => ';', 'attributes' => array('maxlength' => 1))
			);
		}
	} else {
		$use_namespace = '\Gastmo';
		function edit_order($data, $files = array()) {
			$res = false;
			if (is_array($files) && isset($files['csv'])) {
				$order = new OrderExporter(isset($_GET['import_qty']) ? $_GET['import_qty'] : null);
				$res = isset($data['file_type']) && is_string($data['file_type']) && $data['file_type'] == 't'
					? $order->importTotals($files['csv'], $data)
					: $order->importQty($files['csv'], $data);
				unset($order);
			}
			return $res;
		}
		$order = new Order(isset($_GET['import_qty']) ? $_GET['import_qty'] : null);
		if ($order->exists()) {
			$data = $order->getData();
			$fields = array(
				array('field' => 'title', 'title' => 'Titolo', 'type' => 'print'),
				array('field' => 'csv', 'title' => 'CSV delle quantità definitive', 'type' => 'file', 'check' => array('type' => '*.csv')),
				array('field' => 'csv_delimiter', 'title' => 'Separatore campi CSV', 'value' => ';', 'attributes' => array('maxlength' => 1)),
				array('field' => 'file_type', 'title' => 'Tipo', 'type' => 'radio', 'value' => array(
					array('value' => 'q', 'label' => 'Quantità'),
					array('value' => 't', 'label' => 'Totali')
				), 'selected' => 'q'),
				array('field' => 'status', 'title' => 'Stato', 'type' => 'radio', 'value' => array(
					array('value' => Order::STATUS_OPEN, 'label' => 'Aperto'),
					array('value' => Order::STATUS_DELIVERING, 'label' => 'In Consegna'),
					array('value' => Order::STATUS_DELIVERED, 'label' => 'Consegnato')
				), 'selected' => Order::STATUS_DELIVERED)
			);
		}
	}
} else {
	$object = '\Gastmo\Order';
	$user_groups = UserGroup::getUserGroups($u->get('id'), 'id');
	if ($edit) {
		$o = new Order($_GET['id']);
		if ($u->get('level') == 'gestione_ordini' && $o->get('user_group') != 0 && !in_array($o->get('user_group'), $user_groups)) {
			redirect('/admin/index.php?page=list_order');
		}
		$is_open = $o->get('status') == Order::STATUS_OPEN;
		unset($o);
	} else {
		$is_open = true;
	}
	$users = $u->getList(array(
		'select' => array(
			array('value' => 'id', 'as' => 'value'),
			array('value' => 'CONCAT(users_data.name, \' \', users_data.surname)', 'as' => 'label', 'no_quote' => true)
		),
		'join' => array(
			'users_data' => array('cond' => array('users_data.id', 'users.id'))
		),
		'order' => array('label' => 'ASC')
	));
	$ug = new UserGroup();
	$groups = $ug->getList(array(
		'select' => array(array('value' => 'id', 'as' => 'value'), array('value' => 'title', 'as' => 'label')),
		'where' => $u->get('level') == 'gestione_ordini' ? array(array('field' => 'id', 'match' => 'IN', 'value' => $user_groups)) : null,
		'order' => array('title' => 'ASC')
	));
	unset($ug, $user_groups);
	$fields = array_merge(array(
		array('field' => 'title', 'title' => 'Titolo'),
		array('field' => 'descr', 'title' => 'Note', 'type' => 'textarea'),
		array('field' => 'closing_date', 'title' => 'Chiusura ordine', 'type' => 'datetime', 'default' => date('Y-m-d H:i')),
		array('field' => 'shipping_date', 'title' => 'Data distribuzione', 'type' => 'date', 'default' => $edit ? null : date('Y-m-d'), 'can_reset' => true),
		array('field' => 'admin', 'title' => 'Referente', 'type' => 'select', 'value' => $users, 'default' => \User::getLoggedUser())
	), count($groups) == 1 ? array(
		array('field' => 'user_group', 'type' => 'hidden', 'value' => $groups[0]['value']),
		array('field' => 'user_group_print', 'title' => 'Gruppo', 'type' => 'print', 'value' => $groups[0]['label'])
	) : array(
		array('field' => 'user_group', 'title' => 'Gruppo', 'type' => 'select', 'value' => array_merge(
			array(array('value' => '', 'label' => '')),
			$groups
		))
	), array(
		array('field' => 'shipping_cost', 'title' => 'Spese di spedizione', 'type' => 'number', 'attributes' => array('step' => 0.01))
	), $is_open ? array(
		array('field' => 'csv', 'title' => 'CSV di importazione', 'type' => 'file', 'check' => array('type' => '*.csv'), 'description' => $edit ?
			'Ricaricando un altro file verranno cancellati e sovrascritti tutti i prodotti presenti in questo ordine.'
			: ''),
		array('field' => 'csv_delimiter', 'title' => 'Separatore campi CSV', 'value' => ';', 'attributes' => array('maxlength' => 1))
	) : array(), array(
		array('field' => 'export', 'title' => 'Esportazione', 'type' => 'hidden'),
		array('field' => 'status', 'title' => 'Stato', 'type' => 'radio', 'value' => array(
			array('value' => Order::STATUS_OPEN, 'label' => 'Aperto'),
			array('value' => Order::STATUS_DELIVERING, 'label' => 'In Consegna'),
			array('value' => Order::STATUS_DELIVERED, 'label' => 'Consegnato')
		)),
		array('field' => 'online', 'title' => 'Online', 'type' => 'onoff')
	));
	unset($users, $groups, $is_open);
	if ($edit) {
		$order = new OrderExporter($_GET['id']);
		$form_options = array('disabled' => !Order::canEdit($order));
		$fields[] = array('field' => 'link', 'title' => 'Link', 'type' => 'custom', 'value' => '<a href="'.Order::getURL($order->get('id')).'">Visualizza ordine</a>');
		$o = printHtmlTag('p', \Admin::printIcon('add', array(
			'a' => array('href' => 'index.php?page=list_order&create='.$order->get('id'), 'name' => 'export'),
			'text' => 'Nuova esportazione'
		)));
		$exports = $order->getExports();
		$counter = count($exports);
		if ($counter > 0) {
			$export_types = $order->getExportTypes();
			$trs = '';
			for ($i = 0; $i < $counter; $i++) {
				$d = strtotime($exports[$i]['date']);
				$n = trim($exports[$i]['surname'].' '.$exports[$i]['name']);
				$export_tds = '';
				foreach ($export_types as $k => $v) {
					$export_tds .= printHtmlTag(
						'td',
						is_array($v) && isset($v['check']) && \Valid::string($v['check']) && (!isset($exports[$i][$v['check']]) || !\Valid::string($exports[$i][$v['check']]))
							? ''
							: \Admin::printIcon('export', array(
								'a' => array('href' => '/admin/index.php?page=edit_order&id='.$order->get('id').'&download_export='.$k.'&date='.$d)
							))
					);
					unset($k, $v);
				}
				$trs .= printHtmlTag(
					'tr',
					printHtmlTag('td', date('d/m/Y H:i', $d))
					.printHtmlTag('td', html($n == '' ? $exports[$i]['username'] : $n))
					.$export_tds
					.printHtmlTag('td', \Admin::printIcon('refresh', array('a' => array('onclick' => 'order_export.refresh('.$d.', this);return false;'))))
					.printHtmlTag('td', \Admin::printIcon('add', array('a' => array(
						'href' => _ADMINH.'index.php?page=list_order&create='.$order->get('id').'&d='.$d,
						'title' => 'Nuova esportazione partendo da questa'
					))))
					.printHtmlTag('td', printHtmlTag('input', false, array(
						'type' => 'radio',
						'name' => 'export_date',
						'value' => $d,
						'checked' => $order->get('export_date') == $d
					)))
					.printHtmlTag('td', \Admin::printIcon('delete', array('a' => array('onclick' => 'order_export.delete('.$d.', this);return false;'))))
				);
				unset($d, $n);
			}
			unset($i);
			$export_ths = '';
			foreach ($export_types as $v) {
				$export_ths .= printHtmlTag('th', isset($v['title']) ? html($v['title']) : '');
				unset($k, $v);
			}
			$o .= printHtmlTag('table', printHtmlTag('thead', printHtmlTag(
				'tr',
				printHtmlTag('th', 'Data')
				.printHtmlTag('th', 'Utente')
				.$export_ths
				.printHtmlTag('th', '')
				.printHtmlTag('th', '')
				.printHtmlTag('th', '')
				.printHtmlTag('th', '')
			)).printHtmlTag('tbody', $trs), array('class' => 'table table-condensed'));
			unset($export_ths, $trs);
			ob_start();
			?>
			<script type="text/javascript">
			var order_export = {
				'refresh': function(d, el) {
					admin.ajaxOperation({
						'url': _ROOT  + 'index.php?page=edit_order',
						'data': {
							'id': <?php echo $order->get('id');?>,
							'refresh_export': d
						}
					}, {
						'onSuccess': function() {
							window.location.reload();
						}
					});
				},
				'delete': function(d, el) {
					if (confirm('Sei sicuro di voler cancellare questa esportazione?')) {
						admin.ajaxOperation({
							'url': _ROOT  + 'index.php?page=edit_order',
							'data': {
								'id': <?php echo $order->get('id');?>,
								'delete_export': d
							}
						}, {
							'onSuccess': function() {
								var tr = el.closest('tr');
								if (tr) {
									tr.remove();
								}
							}
						});
					}
				}
			};
			</script>
			<?php
			$page->setHeadInclude(ob_get_contents());
			ob_end_clean();
		}
		unset($counter, $exports, $order);
		$fields[] = array('field' => 'export_date', 'type' => 'hidden', 'value' => 0);
		$fields[] = array('field' => 'exports', 'title' => 'Esportazione', 'type' => 'custom', 'value' => $o);
		unset($o);
	}
}
unset($u);