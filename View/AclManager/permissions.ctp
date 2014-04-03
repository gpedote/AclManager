<div id="page-container" class="row">
	
	<div id="sidebar" class="col-sm-3">
		
		<div class="actions">
			
			<ul class="list-group">	

<?php 
$aroModels = Configure::read("AclManager.aros"); 
$aroModels = (empty($aroModels)) ? (array()) : ($aroModels);
$dataLevel = 0;

foreach ($aroModels as $aroModel): ?>
	<li class="list-group-item">
		<?php echo $this->Html->link(__('%s Permissions', $aroModel), array('aro' => $aroModel)); ?>
	</li>
<?php endforeach; ?>
		<li class="list-group-item"><?php echo $this->Html->link(__('Update ACOs'), array('action' => 'updateAcos')); ?></li>
		<li class="list-group-item"><?php echo $this->Html->link(__('Update AROs'), array('action' => 'updateAros')); ?></li>
		<!--<li class="list-group-item"><?php echo $this->Html->link(__('Drop ACOs/AROs'), array('action' => 'drop'), array(), __("Do you want to drop all ACOs and AROs?")); ?></li>
		<li class="list-group-item"><?php echo $this->Html->link(__('Drop permissions'), array('action' => 'dropPerms'), array(), __("Do you want to drop all the permissions?")); ?></li>-->
				
			</ul><!-- /.list-group -->
			
		</div><!-- /.actions -->
		
	</div><!-- /#sidebar .span3 -->

	<div id="page-content" class="col-sm-9">

		<div class="permissions index">
	
		<div class="page-header">
			<h2><?php echo __("%s permissions", $aroAlias); ?></h2>
		</div>

<div class="form">
<?php echo $this->Form->create('Perms'); ?>
<div class="table-responsive">
<table cellpadding="0" cellspacing="0" class="table table-bordered">
	<thead>
		<tr>
			<th>Action</th>
			<?php foreach ($aros as $aro): ?>
			<?php $aro = array_shift($aro); ?>
			<th><?php echo h($aro[$aroDisplayField]); ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
<?php foreach ($acos as $id => $aco):
	$action = $aco['Action'];

	// Check if is a parent ACO
	$isParent = (substr_count($action, '/') === 1) ? (true) : (false);
	if ($isParent) {
		$dataLevel++;
	}
?>
	<tr class="<?php echo ($isParent) ? ('active isbold') : (''); ?>">
		<td>
			<?php echo h($aco['Aco']['alias']); ?>
		</td>

<?php foreach ($aros as $aro): 
	$inherit = $this->Form->value("Perms." . str_replace("/", ":", $action) . ".{$aroAlias}:{$aro[$aroAlias]['id']}-inherit");
	$allowed = $this->Form->value("Perms." . str_replace("/", ":", $action) . ".{$aroAlias}:{$aro[$aroAlias]['id']}"); 
	$value = ($inherit) ? ('inherit') : (null); 

	$icon = '';
	if ($inherit) {
		$icon = 'fa fa-arrow-up';
	} else {
		$icon = ($allowed) ? ('fa fa-check') : ('fa fa-times');
	}
	
 ?>

<td isparent="<?php echo $isParent ? 'true' : 'false'; ?>" 
	data-level='<?php echo $dataLevel; ?>' 
	data-parent="<?php echo "{$aroAlias}:{$aro[$aroAlias]['id']}"; ?>">
<i class="<?php echo $icon;?>"></i>
<?php echo $this->Form->select("Perms." . str_replace("/", ":", $action) . ".{$aroAlias}:{$aro[$aroAlias]['id']}", 
	array(
		array(
			'inherit' => __('Inherit'), 
			'allow' => __('Allow'), 
			'deny' => __('Deny')
		)
	), array('empty' => __('No change'), 'value' => $value)); ?>
</td>
<?php endforeach; ?>

	</tr>
		
<?php endforeach; ?>
	</tbody>
</table>

<div class="bordered">
	<?php echo $this->Form->button(__('Save Changes'), 
				array('class' => 'btn btn-primary', 'title' => __('Save Changes'))); 
	?>
</div>
	<?php echo $this->Form->end(); ?>
</div><!-- /.table-responsive -->

<p><small>
<?php
	echo $this->Paginator->counter(array(
	'format' => __('Page {:page} of {:pages}, showing {:current} records out of {:count} total, starting on record {:start}, ending on {:end}')
	));
?></small></p>

	<ul class="pagination">
		<?php
		echo $this->Paginator->prev('< ' . __('Previous'), array('tag' => 'li'), null, array('class' => 'disabled', 'tag' => 'li', 'disabledTag' => 'a'));
		echo $this->Paginator->numbers(array('separator' => '', 'currentTag' => 'a', 'tag' => 'li', 'currentClass' => 'disabled'));
		echo $this->Paginator->next(__('Next') . ' >', array('tag' => 'li'), null, array('class' => 'disabled', 'tag' => 'li', 'disabledTag' => 'a'));
		?>
	</ul><!-- /.pagination -->
</div>

<?php echo $this->Html->script('/AclManager/js/changePermissionsIcons.js'); ?>

		</div><!-- /.index -->
	
	</div><!-- /#page-content .col-sm-9 -->

</div><!-- /#page-container .row-fluid -->