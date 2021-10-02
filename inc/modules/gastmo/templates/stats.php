<h1><?php echo html($title);?></h1>
<?php if (empty($data)) : ?>
	<p class="alert alert-info">Nessuna statistica presente.</p>
<?php else : ?>
	<div class="table-responsive">
		<table class="table table-condensed table-bordered">
			<thead>
				<tr>
					<th scope="row"><?php echo html($data_title);?></th>
					<?php foreach ($data as $v) : ?>
						<th>
							<?php if (isset($v['label_url']) && $v['label_url']) : ?>
								<?php echo printHtmlTag('a', html($v['label']), array('href' => $v['label_url']));?>
							<?php else : ?>
								<?php echo html($v['label']);?>
							<?php endif;?>
						</th>
					<?php endforeach;?>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="row">Totale</th>
					<?php foreach ($totals as $total) : ?>
						<td><?php echo printMoney($total);?></td>
					<?php endforeach;?>
				</tr>
			</tfoot>
			<tbody>
				<?php foreach ($groups as $group) : ?>
					<?php if ($group['print']) : ?>
						<tr>
							<th scope="row"><?php echo html($group['title']);?></th>
							<?php foreach ($data as $v) : ?>
								<td><?php if (isset($v['values'][$group['id']])) : ?><?php echo printMoney($v['values'][$group['id']]);?><?php endif;?></td>
							<?php endforeach;?>
						</tr>
					<?php endif;?>
				<?php endforeach;?>
			</tbody>
		</table>
	</div>
<?php endif;?>