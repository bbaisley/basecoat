<?php
if (\Basecoat\Config::$use_pretty_urls) {
	$task_link	= 'task/?';
} else {
	$task_link	= '?page=task&';
}

if ( count($this->pastdue)>0 ) {
	echo '<h3 style="color:red">Past Due ' . ' ('.count($this->pastdue).')</h3>'; 

	$this->tasklist_title	= 'Past Due';
	$this->tasklist			= $this->pastdue;
	include( BC_TEMPLATES . 'common/tasklist.tpl.php');
}
?>

<h3>To Do ({{:todo_count}})</h3>
<?php
$this->tasklist	= $this->todo;

include( BC_TEMPLATES . 'common/tasklist.tpl.php');


