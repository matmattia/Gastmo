<?php
namespace Gastmo;

if (isset($_GET['export'])) {
	$order = new OrderExporter($_GET['export']);
	$csv = $order->exportCartsCSV();
	unset($order);
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="order_export.csv"');
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	header('Content-Length: '.strlen($csv));
	echo $csv;
	unset($csv);
	exit();
} else if (isset($_GET['export_input_csv'])) {
	$csv = array();
	if (is_numeric($_GET['export_input_csv']) && $_GET['export_input_csv'] > 0) {
		$c = new Category();
		$categories = $c->getList(array(
			'where' => array(array('field' => 'order', 'value' => $_GET['export_input_csv'])),
			'order' => array('pos' => 'ASC')
		));
		unset($c);
		$p = new Product();
		$counter = count($categories);
		for ($i = 0; $i < $counter; $i++) {
			$csv[] = array($categories[$i]['title']);
			$products = $p->getList(array(
				'where' => array(array('field' => 'category', 'value' => $categories[$i]['id'])),
				'order' => array('pos' => 'ASC')
			));
			$counter2 = count($products);
			for ($j = 0; $j < $counter2; $j++) {
				$csv[] = array(
					$products[$j]['title'],
					'',
					'',
					$products[$j]['qty_package'],
					$products[$j]['um'],
					is_numeric($products[$j]['price']) ? str_replace('.', ',', floatval($products[$j]['price'])) : '',
					$products[$j]['maker'],
					$products[$j]['location'],
					is_numeric($products[$j]['vat']) ? str_replace('.', ',', floatval($products[$j]['vat'])) : '',
					is_numeric($products[$j]['package_price']) ? str_replace('.', ',', floatval($products[$j]['package_price'])) : '',
					$products[$j]['note']
				);
			}
			unset($j, $counter2, $products);
		}
		unset($i, $counter, $categories, $p);
	}
	$o = arrayToCsv($csv, false);
	unset($csv);
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="order_import.csv"');
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	header('Content-Length: '.strlen($o));
	echo $o;
	unset($o);
	exit();
} else if (isset($_GET['create'])) {
	$order = new OrderExporter($_GET['create']);
	$order_id = intval($order->get('id'));
	if (isset($_POST['change_packages'])) {
		echo json_encode(array(
			'ok' => 1,
			'carts' => $order->checkProductPackages($_POST['change_packages'], $order->getCarts(), false, isset($_POST['tot_packages']) ? $_POST['tot_packages'] : null, isset($_POST['round']) ? $_POST['round'] : null, isset($_POST['fixed']) ? $_POST['fixed'] : null)
		));
		exit();
	}
	if (isset($_POST['saveCartValue'])) {
		$res = array('ok' => 0);
		if (is_numeric($_POST['saveCartValue']) && $_POST['saveCartValue'] >= 0) {
			$ok = Order::saveCartProduct(
				isset($_POST['user']) ? $_POST['user'] : null,
				$order_id,
				isset($_POST['product']) ? $_POST['product'] : null,
				$_POST['saveCartValue'],
				true
			);
			if ($ok) {
				$res['ok'] = 1;
				$qty = \DB::queryOne(\DB::createSql(array(
					'select' => array('qty'),
					'table' => 'carts',
					'where' => array(
						array('field' => 'user', 'value' => $_POST['user']),
						array('field' => 'order', 'value' => $order_id),
						array('field' => 'product', 'value' => $_POST['product'])
					)
				)));
				$res['value'] = $qty ? (float)$qty : 0;
				unset($qty);
			}
			unset($ok);
		}
		echo json_encode($res);
		exit();
	}
	if (isset($_POST['q']) && isset($_POST['tot_packages'])) {
		$o = $order->exportHTML($_POST['q'], $_POST['tot_packages']);
		header('Content-Type: text/html');
		header('Content-Disposition: attachment; filename="'.str_replace(array('"', '/', '\\'), '', $order->get('title')).'.html"');
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: '.strlen($o));
		echo $o;
		unset($o);
		exit();
	}
	$orders = $order->getCarts(isset($_GET['d']) ? $_GET['d'] : null);
	$counter = count($orders);
	if ($counter > 0) {
		ob_start();
		?>
		<style type="text/css">
		#create_order tbody tr.product td{
			background-color: #AFA;
		}
		#create_order tbody tr.product td.highlight{
			background-color: #8C8;
		}
		#create_order tbody tr td.diff{
			text-align: center;
		}
		</style>
		<script type="text/javascript">
		var order_create = {
			'init': function() {
				var that = this, els = null, l = 0, i = 0;
				/* Creo il campo di testo per modificare la quantità ordinata */
				els = document.querySelectorAll('[data-operation="edit-original-qty"]');
				l = els.length;
				for (i = 0; i < l; i++) {
					els[i].addEventListener('click', function(e) {
						var td = this.closest('.original_qty'), s = null, inp = null;
						e.preventDefault();
						if (td) {
							s = td.querySelector('span');
							if (s) {
								inp = admin.createElement('input', {
									'type': 'number',
									'value': s.innerText,
									'step': 'any',
									'class': 'form-control'
								}, {
									'blur': function() {
										that.saveUserProductOrderQty(this, s);
									},
									'keypress': function(e) {
										if (e.keyCode == 13) {
											e.preventDefault();
											that.saveUserProductOrderQty(this, s);
										}
									}
								});
								td.prepend(inp);
								inp.focus();
								s.style.display = 'none';
							}
						}
					});
				}
				/* Visualizzo il campo di testo per modificare la quantità da ordinare */
				els = document.querySelectorAll('[data-operation="edit-new-qty"]');
				l = els.length;
				for (i = 0; i < l; i++) {
					els[i].addEventListener('click', function(e) {
						var td = this.closest('td'), s = td ? td.querySelector('span') : null, inp = td ? td.querySelector('input') : null;
						e.preventDefault();
						if (s && inp) {
							s.style.display = 'none';
							inp.setAttribute('type', 'number');
							if (!inp.dataset.onceEdited) {
								inp.setAttribute('step', 'any');
								inp.classList.add('form-control');
								inp.addEventListener('blur', function() {
									that.editUserProductOrderQty(this);
								});
								inp.addEventListener('keypress', function(e) {
									if (e.keyCode == 13) {
										e.preventDefault();
										that.editUserProductOrderQty(this);
									}
								});
								inp.dataset.onceEdited = 1;
							}
						}
					});
				}
			},
			'updateProduct': function(b) {
				var that = this, tr = b ? b.closest('tr') : null, p = null,
					fixed = [], fixed_inp = null, l = 0, i = 0;
				try {
					p = JSON.parse(tr.getAttribute('data-product'));
				} catch {
					p = null;
				}
				if (p && p.id) {
					fixed_inp = document.querySelectorAll('input[name="fixed_qty_' + p.id + '"]:checked');
					l = fixed_inp.length;
					for (i = 0; i < l; i++) {
						fixed.push(fixed_inp[i].value);
					}
				}
				admin.ajaxOperation({
					'url': _ROOT + 'index.php?page=list_order&create=<?php echo $order_id;?>',
					'data': {
						'change_packages': p.id,
						'tot_packages': tr.querySelector('input[name^="tot_packages"]').value,
						'round': tr.querySelector('input[name="round"]').value,
						'fixed': fixed
					},
					'onComplete': function(d) {
						var l = d && d.ok && d.ok == 1 && d.carts && d.carts.length, i = 0;
						for (i = 0; i < l; i++) {
							if (i == 0) {
								that.setProductQtyDiff(d.carts[i].product_id, d.carts[i].tot_qty_diff);
							}
							that.updateProductUser(d.carts[i]);
						}
					}
				});
			},
			'updateProductUser': function(c) {
				var tr = document.getElementById('product_' + c['user_id'] + '_' +c['product_id']);
				tr.querySelector('.q').innerText = c['product_new_quantity'];
				tr.querySelector('input[name^="q"]').value = c['product_new_quantity'];
				this.setRowDiff(tr, c['product_diff_quantity']);
			},
			'getProductUserData': function(el) {
				var p = null, h = null;
				if (el.tagName != 'TR') {
					el = el.closest('tr');
				}
				if (el) {
					p = el.getAttribute('data-product');
				}
				try {
					p = JSON.parse(p);
				} catch {
					p = null;
				}
				if (p && p.id) {
					h = document.getElementById('product_' + p.id);
					if (h) {
						try {
							p2 = JSON.parse(h.getAttribute('data-product'));
						} catch {
							p2 = null;
						}
						if (p2) {
							Object.assign(p, p2);
						}
					}
					
				}
				return p;
			},
			'saveUserProductOrderQtyXhr': null,
			/* Salva la quantità ordinata */
			'saveUserProductOrderQty': function(inp, s) {
				var that = this, product = this.getProductUserData(inp),
					replace_input_span = function(is_success) {
						s.style.display = '';
						inp.dataset.erasing = true;
						inp.remove();
						if (is_success) {
							alert('Salvataggio avvenuto correttamente.');
						} else {
							alert('Ci sono stati dei problemi nel salvataggio.');
						}
					};
				if (inp.dataset.erasing) {
					return;
				}
				if (this.saveUserProductOrderQtyXhr) {
					this.saveUserProductOrderQtyXhr.abort();
				}
				this.saveUserProductOrderQtyXhr = admin.ajaxOperation({
					'url': _ROOT + 'index.php?page=list_order&create=<?php echo $order_id;?>',
					'data': {
						'saveCartValue': inp.value,
						'user': product ? product.user_id : 0,
						'product': product ? product.id : 0
					}
				}, {
					'onSuccess': function(d) {
						if (d && d.ok == 1) {
							s.innerText = d.value;
							that.updateProduct(document.querySelector('#product_' + (product ? product.id : 0) + ' .update_product_button'));
							replace_input_span.call(null, true);
						} else {
							replace_input_span.call(null, false);
						}
					},
					'onError': function() {
						replace_input_span.call(null, false);
					}
				});
			},
			/* Modifica la quantità da ordinare */
			'editUserProductOrderQty': function(inp) {
				var td = inp.closest('td'), s = td ? td.querySelector('.q') : null, tr = td ? td.closest('tr') : null,
					product = this.getProductUserData(tr), p_tr = document.getElementById('product_' + product.id),
					t = tr.closest('table'), qties = t ? t.querySelectorAll('tr.product_' + product.id + ' .q') : [], qties_l = qties.length,
					v = this.checkNumber(inp.value), diff = this.checkNumber(v - tr.querySelector('.original_qty').innerText),
					total_diff = p_tr.querySelector('input[name^="tot_packages"]').value * product.qty_package,
					i = 0;
				if (s) {
					s.innerText = v;
					s.style.display = '';
				}
				if (inp) {
					inp.setAttribute('type', 'hidden');
				}
				this.setRowDiff(tr, diff);
				for (i = 0; i < qties_l; i++) {
					total_diff -= qties[i].innerText;
				}
				this.setProductQtyDiff(product.id, total_diff * -1);
			},
			'setProductQtyDiff': function(pid, qty) {
				var tr = document.getElementById('product_' + pid), s = tr ? tr.querySelector('.tot_qty_diff') : null, p = s ? s.closest('p') : null;
				if (s) {
					qty = this.checkNumber(qty);
					s.innerText = qty;
					if (p) {
						if (qty == 0) {
							p.classList.remove('alert-danger');
							p.classList.add('alert-success');
						} else {
							p.classList.remove('alert-success');
							p.classList.add('alert-danger');
						}
					}
				}
			},
			'setRowDiff': function(tr, qty) {
				var td = tr ? tr.querySelector('.diff') : null;
				if (td) {
					qty = this.checkNumber(qty);
					td.innerText = (qty > 0 ? '+' : '') + qty;
					if (qty == 0) {
						td.classList.remove('table-warning');
					} else {
						td.classList.add('table-warning');
					}
				}
			},
			'checkNumber': function(n) {
				return Number.parseFloat(Number.parseFloat(n).toFixed(3));
			}
		};
		
		document.addEventListener('DOMContentLoaded', function() {
			order_create.init();
		});
		</script>
		<?php
		$page->setHeadInclude(ob_get_contents());
		ob_end_clean();
		if ($order->get('status') == Order::STATUS_OPEN && $order->get('online') == 1) {
			echo \Admin::printMessage('L&rsquo;ordine &egrave; ancora aperto.', 'error');
		}
		?>
		<form action="index.php?page=list_order&amp;create=<?php echo $order_id;?>" method="post">
			<button type="submit" disabled="disabled" style="display:none" aria-hidden="true"></button>
			<table id="create_order" class="table table-sm table-bordered">
				<tfoot>
					<tr><td colspan="8" class="text-right"><button type="submit" class="btn btn-primary">Esporta</button></td></tr>
				</tfoot>
				<tbody>
				<?php
				$prev_product = 0;
				for ($i = 0; $i < $counter; $i++) {
					$orders[$i]['product_id'] = (int)$orders[$i]['product_id'];
					if ($orders[$i]['product_id'] != $prev_product) {
						$orders[$i]['qty_package'] = (float)$orders[$i]['qty_package'];
						?>
						<tr class="product" id="product_<?php echo $orders[$i]['product_id'];?>" data-product="<?php echo html(json_encode(array('id' => $orders[$i]['product_id'], 'qty_package' => $orders[$i]['qty_package'])));?>">
							<td colspan="3" rowspan="2">
								<b><?php echo html($orders[$i]['product_name']);?></b>
								<?php if (trim($orders[$i]['product_note']) != '') : ?><br /><?php echo html($orders[$i]['product_note']);?><?php endif;?>
							</td>
							<td rowspan="2"<?php if ($orders[$i]['product_um'] != 'KG') : ?> class="highlight"<?php endif;?>>
								<b>UM:</b> <?php echo html($orders[$i]['product_um']);?>
								<br /><b>Prezzo:</b> <?php echo printMoney($orders[$i]['product_price']);?>
								<br /><b>Kg o Pz per cassetta:</b> <?php echo $orders[$i]['qty_package'];?>
							</td>
							<td><b>Percentuale completamento</b></td>
							<td colspan="4" rowspan="2">
								<div class="form-horizontal">
									<div class="form-group">
										<label class="col-sm-6 control-label">Totale cassette:</label>
										<div class="col-sm-6"><input type="text" name="tot_packages[<?php echo $orders[$i]['product_id'];?>]" value="<?php echo intval($orders[$i]['packages']);?>" class="form-control" /></div>
									</div>
									<div class="form-group">
										<label class="col-sm-6 control-label">Arrotondamento:</label>
										<div class="col-sm-6"><input type="text" name="round" value="<?php echo floatval($orders[$i]['round']);?>" class="form-control" /></div>
									</div>
									<div class="form-group">
										<div class="col-sm-offset-6 col-sm-6"><button type="button" onclick="order_create.updateProduct(this);return false;" class="update_product_button btn btn-secondary">Aggiorna</button></div>
									</div>
								</div>
								<p class="<?php echo $orders[$i]['tot_qty_diff'] == 0 ? 'alert alert-success' : 'alert alert-danger';?>">Differenza quantit&agrave;: <span class="tot_qty_diff"><?php echo ($orders[$i]['tot_qty_diff'] > 0 ? '+' : '').floatval($orders[$i]['tot_qty_diff']);?></span></p>
							</td>
						</tr>
						<tr class="product">
							<td><?php echo round($orders[$i]['perc_packages']);?>%</td>
						</tr>
						<tr>
							<th colspan="4">Utente</th>
							<th>Quantit&agrave; ordinata</th>
							<th>Quantit&agrave; da ordinare</th>
							<th>Fissa q.t&agrave;</th>
							<th>Differenza</th>
						</tr>
						<?php
						$prev_product = $orders[$i]['product_id'];
					}
					$orders[$i]['product_quantity'] = (float)$orders[$i]['product_quantity'];
					$orders[$i]['user_id'] = (int)$orders[$i]['user_id'];
					$orders[$i]['product_new_quantity'] = (float)$orders[$i]['product_new_quantity'];
					?>
					<tr id="product_<?php echo $orders[$i]['user_id'];?>_<?php echo $orders[$i]['product_id'];?>" class="product_<?php echo $orders[$i]['product_id'];?><?php if ($orders[$i]['product_quantity'] >= $orders[$i]['qty_package'] && $orders[$i]['product_quantity'] % $orders[$i]['qty_package'] == 0) : ?> info<?php endif;?>" data-product="<?php echo html(json_encode(array('id' => $orders[$i]['product_id'], 'user_id' => $orders[$i]['user_id'])));?>">
						<td><?php echo $orders[$i]['user_id'];?></td>
						<td><?php echo html($orders[$i]['username']);?></td>
						<td><?php echo html($orders[$i]['name']);?></td>
						<td><?php echo html($orders[$i]['email']);?></td>
						<td class="original_qty"><span><?php echo $orders[$i]['product_quantity'];?></span> <?php echo \Admin::printIcon('edit', array('a' => array('data-operation' => 'edit-original-qty')));?></td>
						<td>
							<span class="q"><?php echo $orders[$i]['product_new_quantity'];?></span>
							<input type="hidden" name="q[<?php echo $orders[$i]['user_id'];?>][<?php echo $orders[$i]['product_id'];?>]" value="<?php echo $orders[$i]['product_new_quantity'];?>" data-qty="<?php echo $orders[$i]['product_quantity'];?>" />
							<?php echo \Admin::printIcon('edit', array('a' => array('data-operation' => 'edit-new-qty')));?>
						</td>
						<td><input type="checkbox" name="fixed_qty_<?php echo $orders[$i]['product_id'];?>" value="<?php echo $orders[$i]['user_id'];?>" /></td>
						<td class="diff<?php if ($orders[$i]['product_diff_quantity'] != 0) : ?> table-warning<?php endif;?>"><?php echo ($orders[$i]['product_diff_quantity'] > 0 ? '+' : '').floatval($orders[$i]['product_diff_quantity']);?></td>
					</tr>
					<?php
				}
				unset($i, $prev_product);
				?>
				</tbody>
			</table>
		</form>
		<?php
	} else {
		echo \Admin::printMessage('Nessun ordine inserito.', 'error');
	}
	unset($counter, $orders);
	$no_print_list = true;
} else if (isset($_GET['votes'])) {
	$qs = array(
		'votes' => isset($_GET['votes']) && is_numeric($_GET['votes']) ? intval($_GET['votes']) : 0,
		'type' => isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : ''
	);
	$title = '';
	$orders = array();
	switch (isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : null) {
		case 'usergroup':
			$usergroup = new UserGroup($_GET['votes']);
			if ($usergroup->exists()) {
				$title = $usergroup->get('title');
				$sql = array(
					'select' => array('id'),
					'where' => array(
						array('field' => 'user_group', 'value' => $usergroup->get('id'))
					)
				);
				if (isset($_GET['year']) && is_numeric($_GET['year']) && $_GET['year'] > 0) {
					$sql['where'][] = array('field' => 'shipping_date', 'match' => '>=', 'value' => date('Y-m-d H:i:s', mktime(0, 0, 0, 1, 1, $_GET['year'])));
					$sql['where'][] = array('field' => 'shipping_date', 'match' => '<=', 'value' => date('Y-m-d H:i:s', mktime(23, 59, 59, 12, 31, $_GET['year'])));
					$qs['year'] = intval($_GET['year']);
				}
				$order = new Order();
				$orders = \DB::queryCol($order->getSql($sql));
				unset($order, $sql);
			}
			unset($usergroup);
		break;
		default:
			$order = new OrderExporter($_GET['votes']);
			if ($order->exists()) {
				$title = $order->get('title');
				$orders = array($order->get('id'));
			}
			unset($order);
		break;
	}
	if (empty($orders)) {
		echo \Admin::printMessage('Ordine inesistente.', 'error');
	} else {
		/**
		 * Stampa una quantità nelle votazioni degli ordini
		 * @param float $q quantità
		 * @return string
		 */
		function printOrderVotesQty($q) {
			return str_replace('.', ',', is_numeric($q) ? floatval($q) : 0);
		}
		if (isset($_REQUEST['product_users'])) {
			$json = array('ok' => 0);
			$products = is_array($_REQUEST['product_users']) ? array_values($_REQUEST['product_users']) : array($_REQUEST['product_users']);
			$counter = count($products);
			for ($i = 0; $i < $counter; $i++) {
				if (!is_numeric($products[$i]) || $products[$i] <= 0) {
					unset($products[$i]);
				}
			}
			if (!empty($products)) {
				$users = \DB::queryRows(array(
					'select' => array(
						'users.username',
						'users_data.surname',
						'users_data.name'
					),
					'table' => 'users_data',
					'join' => array(
						'users' => array('cond' => array('users_data.id', 'users.id'), 'type' => 'left'),
						'carts' => array('carts.user', 'users.id')
					),
					'where' => array(
						array('field' => 'carts.order', 'match' => 'IN', 'value' => $orders),
						array('field' => 'carts.product', 'match' => 'IN', 'value' => $products),
						array('field' => 'carts.vote', 'match' => 'IS NOT')
					),
					'group' => 'users.id',
					'order' => array(
						'users_data.surname' => 'ASC',
						'users_data.name' => 'ASC',
						'users.username' => 'ASC'
					)
				));
				if (is_array($users)) {
					$json['ok'] = 1;
					$json['users'] = array();
					$counter = count($users);
					for ($i = 0; $i < $counter; $i++) {
						$json['users'][] = trim($users[$i]['surname']) != '' || trim($users[$i]['name']) != ''
							? trim($users[$i]['surname'].' '.$users[$i]['name'])
							: $users[$i]['username'];
					}
					unset($i, $counter);
				}
				unset($users);
			}
			unset($products);
			echo json_encode($json);
			unset($json);
			exit();
		}
		$p = new Product();
		$products = $p->getList(array(
			'select' => array(
				array('value' => 'products.*', 'no_quote' => true),
				'products_votes.waste',
				'products_votes.vote',
				'products_votes.vote_descr',
				array('value' => 'SUM(carts.actual_qty)', 'no_quote' => true, 'as' => 'qty')
			),
			'join' => array(
				'categories' => array('categories.id', 'products.category'),
				'products_votes' => array('type' => 'left', 'cond_type' => 'where', 'cond' => array(
					array('field' => 'products_votes.product', 'value' => 'products.id', 'value_type' => 'field'),
					array('field' => 'products_votes.order', 'match' => 'IN', 'value' => $orders)
				)),
				'carts' => array('type' => 'left', 'cond_type' => 'where', 'cond' => array(
					array('field' => 'carts.product', 'value' => 'products.id', 'value_type' => 'field'),
					array('field' => 'carts.order', 'match' => 'IN', 'value' => $orders)
				))
			),
			'where' => array(
				'groups' => array(
					array(
						array('field' => 'categories.order', 'match' => 'IN', 'value' => $orders)
					),
					array(
						array('field' => 'products.id', 'match' => 'IN', 'value' => '('.\DB::createSql(array(
							'select' => array(array(
								'value' => 'DISTINCT(carts.product)',
								'no_quote' => true
							)),
							'table' => 'carts',
							'where' => array(
								'groups' => array(
									array(
										array('field' => 'carts.order', 'match' => 'IN', 'value' => $orders)
									),
									array(
										array('field' => 'carts.vote', 'match' => 'IS NOT', 'operator' => 'OR'),
										array('field' => 'carts.vote_descr', 'match' => '<>', 'value' => '', 'operator' => 'OR')
									)
								)
							)
						)).')', 'value_type' => 'sql', 'operator' => 'OR'),
						array('field' => 'products_votes.product', 'match' => 'IS NOT', 'operator' => 'OR')
					)
				)
			),
			'group' => array('products.id'),
			'order' => count($orders) == 1 ? array(
				'categories.pos' => 'ASC',
				'products.pos' => 'ASC'
			) : array(
				'products.title' => 'ASC'
			)
		));
		$packages_export = array();
		if (!empty($products)) {
			$counter = count($orders);
			for ($i = 0; $i < $counter; $i++) {
				$order = new OrderExporter($orders[$i]);
				$export = @json_decode($order->getExport('json'), true);
				unset($order);
				if (is_array($export) && isset($export['packages']) && is_array($export['packages'])) {
					foreach ($export['packages'] as $product => $qty) {
						if (is_numeric($qty)) {
							if (!isset($packages_export[$product])) {
								$packages_export[$product] = 0;
							}
							$packages_export[$product] += $qty;
						}
						unset($product, $qty);
					}
				}
				unset($export);
			}
			unset($i, $counter);
			$prepared = 'get_products_carts_votes';
			\DB::prepare($prepared, \DB::createSql(array(
				'table' => 'carts',
				'where' => array(
					'groups' => array(
						array(
							array('field' => 'order', 'match' => 'IN', 'value' => '('.implode(',', array_fill(0, count($orders), '?')).')', 'value_type' => 'sql'),
							array('field' => 'product', 'value' => '?', 'value_type' => 'sql')
						),
						array(
							array('field' => 'vote', 'match' => 'IS NOT', 'operator' => 'OR'),
							array('field' => 'vote_descr', 'match' => '<>', 'value' => '', 'operator' => 'OR')
						)
					)
				),
				'order' => array(
					'vote' => 'ASC'
				)
			)));
			$arr = $products;
			$products = array();
			foreach ($arr as $v) {
				$k = strtolower(trim($v['title']));
				if (!isset($products[$k])) {
					$products[$k] = array_merge($v, array(
						'ids' => array(),
						'qty' => 0,
						'vote' => array(
							'tot' => 0,
							'num' => 0
						),
						'waste' => 0,
						'votes_descr' => array(),
						'sums' => array(
							'qty' => 0,
							'votes' => 0,
							'num_votes' => 0,
							'waste' => 0,
							'votes_1' => 0,
							'votes_5' => 0
						)
					));
				}
				$products[$k]['ids'][] = intval($v['id']);
				$products[$k]['qty'] += $v['qty'];
				if (is_numeric($v['vote']) && $v['vote'] > 0) {
					$products[$k]['vote']['tot'] += $v['vote'];
					$products[$k]['vote']['num']++;
				}
				$products[$k]['waste'] += $v['waste'];
				if ($v['vote_descr'] != '') {
					$products[$k]['votes_descr'][] = $v['vote_descr'];
				}
				if (isset($packages_export[$v['id']])) {
					$products[$k]['sums']['qty'] += $packages_export[$v['id']] * $v['qty_package'];
				}
				$params = $orders;
				$params[] = $v['id'];
				$carts = \DB::queryRows(\DB::execPrepared($prepared, $params));
				unset($params);
				$counter = count($carts);
				for ($i = 0; $i < $counter; $i++) {
					if ($carts[$i]['vote']) {
						$products[$k]['sums']['votes'] += $carts[$i]['vote'];
						$products[$k]['sums']['num_votes']++;
						if ($carts[$i]['vote'] == 1) {
							$products[$k]['sums']['votes_1']++;
						}
						if ($carts[$i]['vote'] == 5) {
							$products[$k]['sums']['votes_5']++;
						}
					}
					if ($carts[$i]['vote_descr'] != '') {
						$products[$k]['votes_descr'][] = intval($carts[$i]['vote']).' &mdash; '.$carts[$i]['vote_descr'];
					}
					if ($carts[$i]['waste']) {
						$products[$k]['sums']['waste'] += $carts[$i]['waste'];
					}
				}
				unset($i, $counter, $carts, $k, $v);
			}
		}
		$tcpdf_path = _CLASS.'tcpdf/tcpdf.php';
		$can_pdf = is_file($tcpdf_path);
		$do_pdf = $can_pdf && isset($_GET['download']) && is_string($_GET['download']) && $_GET['download'] == 'pdf';
		$do_xls = isset($_GET['download']) && is_string($_GET['download']) && $_GET['download'] == 'xls';
		$do_export = $do_pdf || $do_xls;
		if ($do_export) {
			ob_start();
		} else if (!empty($products)) {
			ob_start();
			?>
			<script type="text/javascript">
			function showProductVotesUsers(p) {
				var err_func = function() {
					modal.setContent(admin.createElement('p', {
						'text': 'Nessun utente trovato.'
					}));
				}
				modal.setTitle('Utenti');
				modal.openLoading();
				admin.ajaxOperation({
					'url': _ROOT + 'index.php',
					'method': 'get',
					'data': Object.assign(<?php echo json_encode($qs);?>, {
						'page': 'list_order',
						'product_users': p
					})
				}, {
					'onSuccess': function(r) {
						var l = r.users ? r.users.length : 0, i = 0, lis = [];
						if (l > 0) {
							for (i = 0; i < l; i++) {
								lis.push({
									'tag': 'li',
									'attributes': {
										'text': r.users[i]
									}
								});
							}
							modal.setContent(admin.createElement('ul', null, null, lis));
						} else {
							err_func.call();
						}
					},
					'onError': function() {
						err_func.call();
					}
				});
			}
			</script>
			<?php
			$page->setHeadInclude(ob_get_contents());
			ob_end_clean();
		}
		?>
		<?php if (!$do_xls) : ?>
			<h1><?php echo html($title);?></h1>
		<?php endif;?>
		<?php if (empty($products)) : ?>
			<?php echo \Admin::printMessage('Nessun voto inserito.', 'info');?>
		<?php else : ?>
			<?php if (!$do_export) : ?>
				<?php echo printHtmlTag('p', ($can_pdf ? printHtmlTag('a', 'PDF', array(
					'href' => 'index.php?page=list_order'.(empty($qs) ? '' : '&'.http_build_query($qs)).'&download=pdf',
					'class' => 'btn btn-secondary'
				)).' ' : '').printHtmlTag('a', 'Excel', array(
					'href' => 'index.php?page=list_order'.(empty($qs) ? '' : '&'.http_build_query($qs)).'&download=xls',
					'class' => 'btn btn-secondary'
				)));?>
			<?php endif;?>
			<table <?php if ($do_export) : ?>border="1"<?php else : ?>class="table table-bordered table-hover table-condensed"<?php endif;?>>
				<thead>
					<tr>
						<th rowspan="2">Prodotto</th>
						<th colspan="2" style="text-align:center;">Quantit&agrave;</th>
						<th colspan="2" style="text-align:center;">Valutazione visiva</th>
						<th colspan="3" style="text-align:center;">Valutazione qualitativa</th>
						<th rowspan="2" style="width:6%;text-align:center;">Reclami</th>
						<th rowspan="2" style="width:6%;text-align:center;">Eccellenze</th>
						<th rowspan="2">Note</th>
					</tr>
					<tr>
						<th style="width:6%;text-align:center;">Ordinata</th>
						<th style="width:6%;text-align:center;">Distribuita</th>
						<th style="width:6%;text-align:center;">Voto</th>
						<th style="width:6%;text-align:center;">Scarto</th>
						<th style="width:6%;text-align:center;">Voto medio</th>
						<th style="width:6%;text-align:center;">N&ordm; Voti</th>
						<th style="width:6%;text-align:center;">Scarto</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($products as $v) : ?>
						<?php
						if (empty($v['votes_descr'])) {
							$v['votes_descr'][] = '';
						}
						$counter_votes_descr = count($v['votes_descr']);
						?>
						<tr>
							<td rowspan="<?php echo $counter_votes_descr;?>"><?php echo html($v['title']);?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['sums']['qty'] > 0) : ?><?php echo printOrderVotesQty($v['sums']['qty']);?><?php endif;?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php echo printOrderVotesQty($v['qty']);?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['vote']['num'] > 0) : ?><?php echo printOrderVotesQty(round($v['vote']['tot'] / $v['vote']['num'], 1));?><?php endif;?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['waste'] > 0) : ?><?php echo printOrderVotesQty($v['waste']);?><?php endif;?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['sums']['num_votes'] > 0) : ?><?php echo printOrderVotesQty(round($v['sums']['votes'] / $v['sums']['num_votes'], 1));?><?php endif;?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['sums']['num_votes'] > 0) : ?><?php echo intval($v['sums']['num_votes']);?><?php if (!$do_export) : ?> <?php echo \Admin::printIcon(null, array(
								'icon' => array('bootstrap' => 'people', 'fontawesome' => 'users'),
								'a' => array('onclick' => 'showProductVotesUsers('.json_encode($v['ids']).');return false;', 'title' => 'Utenti')
							));?><?php endif;?><?php endif;?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['sums']['waste'] > 0) : ?><?php echo printOrderVotesQty($v['sums']['waste']);?><?php endif;?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['sums']['votes_1'] > 0) : ?><?php echo intval($v['sums']['votes_1']);?><?php endif;?></td>
							<td rowspan="<?php echo $counter_votes_descr;?>" class="text-center"><?php if ($v['sums']['votes_5'] > 0) : ?><?php echo intval($v['sums']['votes_5']);?><?php endif;?></td>
							<?php for ($i = 0; $i < $counter_votes_descr; $i++ ) : ?>
								<?php if ($i > 0) : ?>
									<tr>
								<?php endif;?>
								<td><?php echo printText($v['votes_descr'][$i]);?></td>
								<?php if ($i < $counter_votes_descr - 1) : ?>
									</tr>
								<?php endif;?>
							<?php endfor;?>
						</tr>
					<?php endforeach;?>
				</tbody>
			</table>
		<?php endif;
		if ($do_pdf) {
			require_once($tcpdf_path);
			$pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
			$pdf->SetAuthor('Gastmo');
			$pdf->SetTitle($title);
			$pdf->SetSubject($title);
			$pdf->SetFont('dejavusans', '', 10);
			$pdf->SetPrintHeader(false);
			$pdf->SetPrintFooter(false);
			$pdf->AddPage();
			$pdf->writeHTML(ob_get_contents(), true, false, true, false, '');
			ob_end_clean();
			$pdf->Output($title.'.pdf', 'D');
			exit();
		} else if ($do_xls) {
			$xls = ob_get_contents();
			ob_end_clean();
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment; filename="'.createLink($title).'.xls"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: '.strlen($xls));
			echo $xls;
			unset($xls);
			exit();
		}
		unset($tcpdf_path, $can_pdf, $do_pdf, $do_xls, $do_export);
	}
	unset($title, $orders, $qs);
	$no_print_list = true;
} else if (isset($_GET['totals'])) {
	$order = new Order($_GET['totals']);
	if ($order->exists()) {
		echo printHtmlTag('h1', $order->get('title'));
		$total = 0;
		$trs = '';
		$users = Order::getUsers($order->get('id'), true);
		foreach ($users as $user) {
			$is_actual_qty = false;
			$tot = $order->getUserTotal($user['id'], null, $is_actual_qty);
			$trs .= printHtmlTag(
				'tr',
				printHtmlTag('td', html($user['alias']))
				.printHtmlTag('td', printMoney($tot))
				.printHtmlTag('td', $is_actual_qty ? 'Totale inserito' : 'Totale non inserito')
			);
			unset($is_actual_qty);
			$total += $tot;
			unset($tot, $user);
		}
		unset($users);
		if ($trs === '') {
			echo \Admin::printMessage('Totali non caricati.', 'info');
		} else {
			echo printHtmlTag('table', printHtmlTag('thead', printHtmlTag('tr', printHtmlTag('th', 'Utente').printHtmlTag('th', 'Totale').printHtmlTag('th', 'Tipo'))).printHtmlTag('tbody', $trs), array('class' => 'table'));
		}
		unset($trs);
		echo printHtmlTag('h2', 'Totale: '.printMoney($total));
		unset($total);
	} else {
		echo \Admin::printMessage('Ordine inesistente.', 'error');
	}
	unset($order);
	$no_print_list = true;
} else {
	$object = '\Gastmo\Order';
	$sql = array(
		'where' => array(),
		'order' => array('id' => 'DESC')
	);
	$u = \User::getLoggedUserObject(true);
	$is_magazziniere = $u->get('level') == 'magazziniere';
	if ($is_magazziniere || $u->get('level') == 'gestione_ordini') {
		$groups = UserGroup::getUserGroups($u->get('id'), 'id');
		$groups[] = 0;
		$sql['where'][] = array('field' => 'user_group', 'match' => 'IN', 'value' => $groups);
		unset($groups);
	}
	unset($u);
	if (isset($_GET['status']) && is_scalar($_GET['status']) && trim($_GET['status']) != '') {
		$sql['where'][] = array('field' => 'status', 'value' => $_GET['status']);
	}
	if (isset($_GET['export_list'])) {
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET');
	}
	$check_func_edit = function($operation, $params) {
		return is_array($params) && isset($params['data']) && is_array($params['data'])
			&& isset($params['data']['id']) && is_numeric($params['data']['id']) && $params['data']['id'] > 0
			&& Order::canEdit($params['data']['id'])
			? $operation
			: null;
	};
	$operations = array(
		'add' => true,
		'export_raw' => array('href' => _ADMINH.'index.php?page=list_order&export={URLENCODE|ID}', 'title' => 'Esporta CSV degli ordini effettuati', 'class_icon' => 'shopping-cart'),
		'create' => array('href' => _ADMINH.'index.php?page=list_order&create={URLENCODE|ID}', 'title' => 'Chiusura ordine', 'check_func' => $check_func_edit),
		'export_input_csv' => array('href' => _ADMINH.'index.php?page=list_order&export_input_csv={URLENCODE|ID}', 'title' => 'Esporta CSV per creare un nuovo ordine', 'icon' => 'page_excel.png'),
		'export' => array('href' => '#', 'onclick' => 'openMultiExport({URLENCODE|ID});return false;', 'title' => 'Esportazione'),
		'import_qty' => array('href' => _ADMINH.'index.php?page=edit_order&import_qty={URLENCODE|ID}', 'title' => 'Importa CSV delle quantità definitive', 'check_func' => $check_func_edit, 'class_icon' => 'file-import'),
		'import_votes' => array('href' => _ADMINH.'index.php?page=edit_order&import_votes={URLENCODE|ID}', 'title' => 'Importa CSV dei giudizi', 'class_icon' => 'vote-yea'),
		'votes' => array('href' => _ADMINH.'index.php?page=list_order&votes={URLENCODE|ID}', 'title' => 'Voti', 'class_icon' => 'poll'),
		'total' => array('href' => _ADMINH.'index.php?page=list_order&totals={URLENCODE|ID}', 'title' => 'Totali', 'class_icon' => 'file-invoice-dollar'),
		'onoff_online' => array('title' => 'Online', 'check_func' => function($operation, $params) {
			return is_array($params) && isset($params['data']) && is_array($params['data'])
				&& isset($params['data']['id']) && is_numeric($params['data']['id']) && $params['data']['id'] > 0
				&& Order::canEditOnline($params['data']['id'])
				? $operation
				: null;
		}),
		'edit' => array('check_func' => $check_func_edit),
		'delete' => array('check_func' => function($operation, $params) {
			return is_array($params) && isset($params['data']) && is_array($params['data'])
				&& isset($params['data']['id']) && is_numeric($params['data']['id']) && $params['data']['id'] > 0
				&& Order::canDelete($params['data']['id'])
				? $operation
				: null;
		})
	);
	if ($is_magazziniere) {
		foreach ($operations as $k => $v) {
			if (!in_array($k, array('export', 'import_qty', 'import_votes', 'votes'))) {
				unset($operations[$k]);
			}
			unset($k, $v);
		}
	}
	unset($is_magazziniere);
	ob_start();
	?>
	<script type="text/javascript">
	function openMultiExport(id) {
		var lis = [];
		modal.setTitle('Esportazione');
		<?php $order = new OrderExporter();?>
		<?php $export_types = $order->getExportTypes();?>
		<?php foreach ($export_types as $k => $v) : ?>
			lis.push({
				'tag': 'li',
				'children': [{'tag': 'a', 'attributes': {
					'href': '?page=edit_order&id=' + id + '&download_export=<?php echo rawurlencode($k);?>#export',
					'html': '<?php echo html($v['title']);?>'
				}}]
			});
		<?php endforeach;?>
		modal.setContent(admin.createElement('ul', null, null, lis));
		modal.open();
	}
	</script>
	<?php
	$page->setHeadInclude(ob_get_contents());
	ob_end_clean();
	$fields = array(
		array('field' => 'title', 'title' => 'Titolo'),
		array('field' => 'closing_date', 'title' => 'Data chiusura', 'transforms' => array('printOrderDate'), 'transforms_params' => array(array(true))),
		array('field' => 'shipping_date', 'title' => 'Data consegna', 'transforms' => 'printOrderDate')
	);
}