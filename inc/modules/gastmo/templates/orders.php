<?php if ($login) : ?>
	<?php if ($is_delivered) : ?>
		<h1>Ordini consegnati</h1>
		<div class="row align-items-stretch">
			<?php foreach ($orders as $v) : ?>
				<div class="col-sm-6 col-md-4 col-lg-3 my-1">
					<div class="card h-100">
						<div class="card-body">
							<h4 class="card-title"><?php if ($v['user_ordered']) : ?><i class="bi bi-check-lg"></i> <?php endif;?><?php echo html($v['title']);?></h4>
							<p class="card-text">Data consegna: <?php echo printOrderDate($v['shipping_date']);?></p>
							<p><a href="<?php echo html($v['url']);?>" class="btn btn-primary" role="button">Apri</a></p>
						</div>
					</div>
				</div>
			<?php endforeach;?>
		</div>
		<?php if ($pagination['pages'] > 1) : ?>
			<nav class="row justify-content-between my-1" aria-label="Paginazione">
				<?php if ($pagination['page'] > 1) : ?><div class="col text-start"><a href="/order/delivered/<?php if ($pagination['page'] > 2) : ?><?php echo $pagination['page'] - 1;?>/<?php endif;?>" class="btn btn-outline-primary"><i class="bi bi-chevron-left" aria-hidden="true"></i> Successivi</a></div><?php endif;?>
				<?php if ($pagination['page'] < $pagination['pages']) : ?><div class="col text-end"><a href="/order/delivered/<?php echo $pagination['page'] + 1;?>/" class="btn btn-outline-primary">Precedenti <i class="bi bi-chevron-right" aria-hidden="true"></i></a></div><?php endif;?>
			</nav>
		<?php endif;?>
	<?php else : ?>
		<div class="row">
			<div class="col-sm-<?php if (empty($delivered_orders)) : ?>6<?php else : ?>4<?php endif;?>">
				<h2>Ordini aperti</h2>
				<div class="list-group">
					<?php if (empty($orders)) : ?>
						<span class="list-group-item">Nessun ordine aperto.</span>
					<?php else : ?>
						<?php foreach ($orders as $v) : ?>
							<a href="<?php echo html($v['url']);?>" class="list-group-item list-group-item-warning">
								<h4><?php if ($v['user_ordered']) : ?><i class="bi bi-check-lg"></i> <?php endif;?><?php echo html($v['title']);?></h4>
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
								<h4><?php if ($v['user_ordered']) : ?><i class="bi bi-check-lg"></i> <?php endif;?><?php echo html($v['title']);?></h4>
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
								<h4><?php if ($v['user_ordered']) : ?><i class="bi bi-check-lg"></i> <?php endif;?><?php echo html($v['title']);?></h4>
								<p>Data consegna: <?php echo printOrderDate($v['shipping_date']);?></p>
							</a>
						<?php endforeach;?>
						<a href="/order/delivered/" class="list-group-item list-group-item-info"><i class="bi bi-chevron-right"></i> Vedi tutti gli ordini consegnati</a>
					</div>
				</div>
			<?php endif;?>
		</div>
	<?php endif;?>
<?php else : ?>
	<?php include(_THEME.'login.php'); ?>
<?php endif;?>