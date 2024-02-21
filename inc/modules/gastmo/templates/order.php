<?php namespace Gastmo;?>
<h1><?php echo html($order['title']);?></h1>
<h2>Prodotti in consegna: <?php if (strtotime($order['shipping_date']) === false) : ?>data da definire<?php else : ?><?php echo date('d/m/Y', strtotime($order['shipping_date']));?><?php endif;?></h2>
<h2>Chiusura ordine: <?php if (strtotime($order['closing_date']) === false) : ?>data da definire<?php else : ?><?php echo date('d/m/Y H:i', strtotime($order['closing_date']));?><?php endif;?></h2>
<?php if ($logged && is_array($admin) && !empty($admin)) : ?>
	<h2>Referente: <?php echo html(isset($admin['name']) && trim($admin['name']) != '' ? $admin['name'] : $admin['username']);?>
	<?php if (isset($admin['phone']) && trim($admin['phone']) != '') : ?>
		(<?php echo html($admin['phone']);?>)
	<?php endif;?>
	</h2>
<?php endif;?>
<?php if (trim($order['descr']) != '') : ?>
	<p><?php echo nl2br(html($order['descr']));?></p>
<?php endif;?>
<?php if ($order['has_actual_qty']) : ?>
	<form action="<?php echo html($order['url']);?>" method="post" id="order_form">
		<table id="order" class="table table-bordered table-hover table-condensed" data-order="<?php echo intval($order['id']);?>">
			<thead>
				<tr>
					<th>Prodotto</th>
					<th>UM</th>
					<th>Q.t&agrave;</th>
					<th>Prezzo</th>
					<th>Totale</th>
					<th>Voto</th>
					<th>Scarto</th>
					<th>Nota</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="4"></td>
					<td><?php echo printMoney($order['price_total']);?></td>
					<td colspan="2"><button type="submit" class="btn btn-primary">Salva</button></td>
				</tr>
			</tfoot>
			<tbody>
				<?php foreach ($products as $v) : ?>
					<tr>
						<td class="title"><?php echo html($v['title']);?></td>
						<td><?php echo html($v['um']);?></td>
						<td><?php echo floatval($v['actual_qty']);?></td>
						<td><?php echo printMoney($v['price']);?></td>
						<td><?php echo printMoney($v['price_total']);?></td>
						<td class="vote text-nowrap">
							<?php if (isset($v['id']) && is_numeric($v['id']) && $v['id'] > 0 && $v['actual_qty'] > 0) : ?>
								<?php foreach (Order::getLabelVotes() as $i => $l) : ?>
									<a href="#" data-product="<?php echo intval($v['id']);?>" data-vote="<?php echo $i;?>" data-toggle="tooltip" title="<?php echo html($l);?>" class="vote_<?php echo $i;?>"><i class="bi bi-star<?php if ($v['vote'] >= $i) : ?>-fill<?php endif;?>" aria-hidden="true"></i><span class="visually-hidden">1</span></a>
								<?php endforeach;?>
								<input type="hidden" name="product_vote[<?php echo intval($v['id']);?>]" value="<?php echo intval($v['vote']);?>" />
							<?php endif;?>
						</td>
						<td class="waste">
							<?php if (isset($v['id']) && is_numeric($v['id']) && $v['id'] > 0 && $v['actual_qty'] > 0) : ?>
								<input type="text" name="product_waste[<?php echo intval($v['id']);?>]" value="<?php echo html($v['waste']);?>" class="form-control" />
							<?php endif;?>
						</td>
						<td class="vote_descr">
							<?php if (isset($v['id']) && is_numeric($v['id']) && $v['id'] > 0 && $v['actual_qty'] > 0) : ?>
								<textarea name="product_vote_descr[<?php echo intval($v['id']);?>]" rows="3" cols="30" class="form-control"><?php echo html($v['vote_descr']);?></textarea>
							<?php endif;?>
						</td>
					</tr>
				<?php endforeach;?>
			</tbody>
		</table>
	</form>
<?php elseif ($order['is_closed']) : ?>
	<?php $is_delivered = $order['status'] == Order::STATUS_DELIVERED;?>
	<table id="order" class="table table-bordered table-hover table-condensed">
		<thead>
			<tr>
				<th>Prodotto</th>
				<th>UM</th>
				<th>Q.t&agrave; ordinata</th>
				<?php if (!$is_delivered) : ?>
					<th>Q.t&agrave; in arrivo</th>
				<?php endif;?>
				<th>Prezzo</th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th scope="row" colspan="<?php if ($is_delivered) : ?>3<?php else : ?>4<?php endif;?>">Totale<?php if ($is_delivered) : ?> effettivo<?php endif;?></th>
				<td><?php echo printMoney($order['order_total']);?></td>
			</tr>
		</tfoot>
		<tbody>
			<?php if (empty($products)) : ?>
				<tr>
					<td colspan="<?php if ($is_delivered) : ?>4<?php else : ?>5<?php endif;?>">Nessun prodotto presente.</td>
				</tr>
			<?php else : ?>
				<?php foreach ($products as $v) : ?>
					<tr>
						<td class="title"><?php echo html($v['title']);?></td>
						<td><?php echo html($v['um']);?></td>
						<td><?php echo floatval($v['qty']);?></td>
						<?php if (!$is_delivered) : ?>
							<td><?php echo floatval($v['export_qty']);?></td>
						<?php endif;?>
						<td><?php echo printMoney($v['price']);?></td>
					</tr>
				<?php endforeach;?>
			<?php endif;?>
		</tbody>
	</table>
<?php else : ?>
	<table id="order" class="table table-bordered table-hover table-condensed" data-order="<?php echo intval($order['id']);?>">
		<thead>
			<tr>
				<th>Prodotto</th>
				<th>Produttore</th>
				<th>Kg o Pz per cassetta</th>
				<th>UM</th>
				<th>Costo al KG o Pz.</th>
				<th>Kg o Pz. Ordinati</th>
				<th>Cassette completate</th>
				<th>Kg o Pz mancanti per completare la cassetta</th>
				<?php if ($shippable || $was_shippable) : ?>
					<th>Kg/Pz</th>
					<th>Totale</th>
				<?php endif;?>
			</tr>
		</thead>
		<?php $colspan = 10 - ($shippable || $was_shippable ? 0 : 2);?>
		<?php if ($shippable || $was_shippable) : ?>
			<tfoot>
				<tr>
					<td colspan="<?php echo $colspan - 1;?>"></td>
					<td id="price_total_order"><?php echo printMoney($order['price_total']);?></td>
				</tr>
				<?php if ($shippable) : ?>
					<tr>
						<td colspan="<?php echo $colspan;?>" class="text-end">
							<button id="toggle_ordered_products" class="btn btn-secondary">Mostra prodotti ordinati</button>
							<button id="save_order" class="btn btn-primary">Salva</button>
						</td>
					</tr>
				<?php endif;?>
			</tfoot>
		<?php endif;?>
		<tbody>
		<?php foreach ($c as $v) : ?>
			<tr id="category-<?php echo intval($v['id']);?>">
				<td colspan="<?php echo $colspan;?>" class="title category position-relative"><?php echo $v['title'];?> <button type="button" data-category="<?php echo intval($v['id']);?>" class="btn btn-link link-light position-absolute top-0 end-0"><i class="bi bi-dash-lg"></i></button></td>
			</tr>
			<?php foreach ($v['products'] as $product) : ?>
				<tr id="product_<?php echo intval($product['id']);?>" itemscope itemtype="http://schema.org/Product" data-product="<?php echo html(json_encode($product));?>" data-category="<?php echo intval($v['id']);?>">
					<td itemprop="name" class="title<?php if (trim($product['note']) != '') : ?> note" data-toggle="tooltip" title="<?php echo html($product['note']);?>"<?php else : ?>"<?php endif;?><?php if (trim($product['color']) != '') : ?> style="background-color:<?php echo html($product['color']);?>"<?php endif;?>><?php echo html($product['title']);?><?php if (trim($product['product_type']) != '') : ?> <span class="product_type">&mdash;<?php echo html($product['product_type']);?>&mdash;</span><?php endif;?></td>
					<td class="maker" itemprop="manufacturer" itemscope itemtype="http://schema.org/Organization"><span itemprop="name"><?php echo html($product['maker']);?></span></td>
					<td><?php echo $product['qty_package'];?></td>
					<td><?php echo html($product['um']);?></td>
					<td><?php echo printMoney($product['price']);?></td>
					<td class="ordered"><?php echo $product['ordered'];?></td>
					<td class="completed<?php if ($product['completed'] == 0 && $product['ordered'] > 0 && $product['ordered'] / $product['qty_package'] < 0.5) : ?> low<?php endif;?>"><?php echo $product['completed'];?></td>
					<td class="to_complete<?php if ($product['to_complete'] == 0) : ?> full<?php endif;?>"><?php echo $product['to_complete'];?></td>
					<?php if ($shippable || $was_shippable) : ?>
						<td class="user_ordered">
							<?php if ($shippable) : ?>
								<input type="text" value="<?php if ($product['user_ordered'] > 0) : ?><?php echo floatval($product['user_ordered']);?><?php endif;?>" inputmode="decimal" min="0" class="form-control" />
							<?php elseif ($product['user_ordered'] > 0) : ?>
								<?php echo floatval($product['user_ordered']);?>
							<?php endif;?>
						</td>
						<td class="price_total" data-val="<?php echo floatval($product['price_total']);?>"><?php if ($product['price_total'] > 0) : ?><?php echo printMoney($product['price_total']);?><?php endif;?></td>
					<?php endif;?>
				</tr>
			<?php endforeach;?>
		<?php endforeach;?>
		</tbody>
	</table>
<?php endif;?>