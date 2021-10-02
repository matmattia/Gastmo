<?php if ($login) : ?>
	<?php if ($is_delivered) : ?>
		<h1>Ordini consegnati</h1>
		<div class="row">
			<?php foreach ($orders as $v) : ?>
				<div class="col-xs-6 col-md-3">
					<div class="thumbnail">
						<div class="caption">
							<h4><?php echo html($v['title']);?></h4>
							<p>Data consegna: <?php echo printOrderDate($v['shipping_date']);?></p>
							<p><a href="<?php echo html($v['url']);?>" class="btn btn-primary" role="button">Apri</a></p>
						</div>
					</div>
				</div>
			<?php endforeach;?>
		</div>
	<?php else : ?>
		<div class="col-sm-<?php if (empty($delivered_orders)) : ?>6<?php else : ?>4<?php endif;?>">
			<h2>Ordini aperti</h2>
			<div class="list-group">
				<?php if (empty($orders)) : ?>
					<span class="list-group-item">Nessun ordine aperto.</span>
				<?php else : ?>
					<?php foreach ($orders as $v) : ?>
						<a href="<?php echo html($v['url']);?>" class="list-group-item list-group-item-warning">
							<h4><?php if ($v['user_ordered']) : ?><span class="glyphicon glyphicon-ok"></span> <?php endif;?><?php echo html($v['title']);?></h4>
							<p>Data chiusura ordine: <?php echo printOrderDate($v['closing_date']);?></p>
						</a>
					<?php endforeach;?>
				<?php endif;?>
			</div>
		</div>
		<div class="col-sm-<?php if (empty($delivered_orders)) : ?>6<?php else : ?>4<?php endif;?>">
			<h2>Ordini in consegna</h2>
			<div class="list-group">
				<?php if (empty($delivering_orders)) : ?>
					<span class="list-group-item">Nessun ordine in consegna.</span>
				<?php else : ?>
					<?php foreach ($delivering_orders as $v) : ?>
						<a href="<?php echo html($v['url']);?>" class="list-group-item list-group-item-success">
							<h4><?php if ($v['user_ordered']) : ?><span class="glyphicon glyphicon-ok"></span> <?php endif;?><?php echo html($v['title']);?></h4>
							<p>Data consegna: <?php echo printOrderDate($v['shipping_date']);?></p>
						</a>
					<?php endforeach;?>
				<?php endif;?>
			</div>
		</div>
		<?php if (!empty($delivered_orders)) : ?>
			<div class="col-sm-4">
				<h2>Ordini consegnati</h2>
				<div class="list-group">
					<?php foreach ($delivered_orders as $v) : ?>
						<a href="<?php echo html($v['url']);?>" class="list-group-item list-group-item-info">
							<h4><?php if ($v['user_ordered']) : ?><span class="glyphicon glyphicon-ok"></span> <?php endif;?><?php echo html($v['title']);?></h4>
							<p>Data consegna: <?php echo printOrderDate($v['shipping_date']);?></p>
						</a>
					<?php endforeach;?>
					<a href="/order/delivered/" class="list-group-item list-group-item-info"><span class="glyphicon glyphicon-chevron-right"></span> Vedi tutti gli ordini consegnati</a>
				</div>
			</div>
		<?php endif;?>
	<?php endif;?>
<?php else : ?>
	<?php include(_THEME.'login.php'); ?>
<?php endif;?>