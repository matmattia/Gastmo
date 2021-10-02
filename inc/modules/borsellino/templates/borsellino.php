<h1><?php echo html($title);?></h1>
<div class="row">
	<?php $div = ceil(12 / count($totals));?>
	<?php foreach ($totals as $v) : ?>
		<div class="col-xs-<?php echo max($div, 6);?> col-sm-<?php echo max($div, 3);?>">
			<section class="panel panel-default">
				<div class="panel-heading"><h1 class="h2 panel-title"><?php echo html($v['title']);?></h1></div>
				<div class="panel-body">
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
	<nav aria-label="Paginazione">
		<ul class="pager">
			<?php if ($page > 1) : ?><li class="previous"><a href="/borsellino/<?php if ($page > 2) : ?><?php echo $page - 1;?>/<?php endif;?>"><span aria-hidden="true">&larr;</span> Successivi</a></li><?php endif;?>
			<?php if ($page < $pages) : ?><li class="next"><a href="/borsellino/<?php echo $page + 1;?>/">Precedenti <span aria-hidden="true">&rarr;</span></a></li><?php endif;?>
		</ul>
	</nav>
<?php endif;?>