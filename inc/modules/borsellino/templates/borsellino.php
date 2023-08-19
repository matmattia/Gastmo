<h1><?php echo html($title);?></h1>
<div class="row">
	<?php $div = ceil(12 / count($totals));?>
	<?php foreach ($totals as $v) : ?>
		<div class="col-xs-<?php echo max($div, 6);?> col-sm-<?php echo max($div, 3);?>">
			<section class="card">
				<h1 class="card-header h2"><?php echo html($v['title']);?></h1>
				<div class="card-body">
					<p class="h2 text-center<?php if ($v['value'] < 0) : ?> text-danger<?php endif;?>"><?php echo number_format($v['value'], 2, ',', '.');?> €</p>
				</div>
			</section>
		</div>
	<?php endforeach;?>
</div>
<h2>Movimenti</h2>
<table class="table">
	<thead>
		<tr>
			<th>Data</th>
			<th>Descrizione</th>
			<th>Entrate</th>
			<th>Uscite</th>
		</tr>
	</thead>
	<tbody>
		<?php if (empty($list)) : ?>
			<tr>
				<td colspan="4">Nessun movimento registrato.</td>
			</tr>
		<?php else : ?>
			<?php foreach ($list as $v) : ?>
				<tr>
					<td><?php echo printDate($v['date'], 'd/m/Y');?></td>
					<td><?php echo html($v['descr']);?></td>
					<td><?php if ($v['income'] != 0) : ?><?php echo number_format($v['income'], 2, ',', '.');?><?php endif;?></td>
					<td><?php if ($v['outflow'] != 0) : ?><?php echo number_format($v['outflow'], 2, ',', '.');?><?php endif;?></td>
				</tr>
			<?php endforeach;?>
		<?php endif;?>
	</tbody>
</table>
<?php if ($pages > 1) : ?>
	<nav class="row justify-content-between" aria-label="Paginazione">
		<?php if ($page > 1) : ?><div class="col text-start"><a href="/borsellino/<?php if ($page > 2) : ?><?php echo $page - 1;?>/<?php endif;?>" class="btn btn-outline-primary"><i class="bi bi-chevron-left" aria-hidden="true"></i> Successivi</a></div><?php endif;?>
		<?php if ($page < $pages) : ?><div class="col text-end"><a href="/borsellino/<?php echo $page + 1;?>/" class="btn btn-outline-primary">Precedenti <i class="bi bi-chevron-right" aria-hidden="true"></i></a></div><?php endif;?>
	</nav>
<?php endif;?>