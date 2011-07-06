<?php
defined ('_JEXEC') or die ('Restricted access');
class HTML_jmsitemap
{
	
	function showMenus($option, &$rows)
	{
		
		?>
		<form action="index.php?option=com_jmsitemap" method="post" name="adminForm">
		<table class="adminlist">
			<thead>
			<tr>
				<th width="10">
						<?php echo JText::_( 'Num' ); ?>
					</th>
				<th width="20">
					<input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo count($rows);?>)" />
				</th>
				<th class="title">Menu Title</th>
				<th class="title">Enabled</th>
				<th class="title"><?php echo JHTML::_('grid.order', $rows);?>Order</th>
				</tr>
				</thead>
				
		<?
		$k =0;
		for($i=0, $n=count($rows); $i < $n; $i++)
		{
			$row = &$rows[$i];
			$checked = JHTML::_('grid.id', $i, $row->id);
			$published = JHTML::_('grid.published', $row, $i);
			?>
			<tr class="<?php echo "row$k"; ?>">
				<td>
						<?php echo $row->id ?>
					</td>
				<td>
					<?php echo $checked; ?>
				</td>
			<td>
				<?php echo $row->title; ?>
			</td>
			<td align="center">
				<?php echo $published ?>
			</td>
			<td class="order">
			<input type="text" name="order[]" size="5" value="<?php echo $row->ordering;?>" enabled class="text_area" style="text-align: center" />
			</td>
			</tr>
			<?
			$k = 1 - $k;
		}
		?>
		</table>
		<input type="hidden" name="option" value="<?php echo $option;?>" />
		<input type="hidden" name="task" value="forms" />
		<input type="hidden" name="boxchecked" value="0" />
		</form>
		<?
			
	}

	function showGoogleXML($option, &$rows)
	{
		
		?>
		<form action="index.php?option=com_jmsitemap" method="post" name="adminForm">
		<table class="adminlist">
			<thead>
			<tr>
				<th width="10">
						<?php echo JText::_( 'Num' ); ?>
					</th>
				<th width="20">
					<input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo count($rows);?>)" />
				</th>
				<th class="title">Menu Title</th>
				<th class="title"><?php echo JHTML::_('grid.order', $rows, 'filesave.png', 'savepriority');?>Priority</th>
				</tr>
				</thead>
				
		<?
		$k =0;
		for($i=0, $n=count($rows); $i < $n; $i++)
		{
			$row = &$rows[$i];
			$checked = JHTML::_('grid.id', $i, $row->id);
			?>
			<tr class="<?php echo "row$k"; ?>">
				<td>
						<?php echo $row->id ?>
					</td>
				<td>
					<?php echo $checked; ?>
				</td>
			<td>
				<?php echo $row->name; ?>
			</td>
			<td class="order">
			<input type="text" name="priority[]" size="5" value="<?php echo $row->priority?>" enabled class="text_area" style="text-align: center" />
			</td>
			</tr>
			<?
			$k = 1 - $k;
		}
		?>
		</table>
		<input type="hidden" name="option" value="<?php echo $option;?>" />
		<input type="hidden" name="task" value="forms" />
		<input type="hidden" name="boxchecked" value="0" />
		</form>
		<?
			
	}
}
	

?>