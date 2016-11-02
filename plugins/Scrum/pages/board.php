<?php

# Copyright (c) 2011 John Reese
# Licensed under the MIT license

require_once( 'icon_api.php' );
require_once( 'scrum_tools.php' );
# 取插件配置参数
$columns = plugin_config_get( 'board_columns' );
$sevcolors = plugin_config_get( 'board_severity_colors' );
$rescolors = plugin_config_get( 'board_resolution_colors' );
$sprint_length = plugin_config_get( 'sprint_length' );

# 取系统中使用的环境变量
$current_project = helper_get_current_project();
$resolved_threshold = config_get( 'bug_resolved_status_threshold' );
$current_date = strtotime(date("Y-m-d")." 00:00:00");
$estimate_field_id = custom_field_get_id_from_name('Estimate_WH');

# 取表单提交的参数  并把 表单参数保存到token
# 取token里面保存的数据
$submit_value = array();
$token_submit = token_get_value( ScrumPlugin::TOKEN_SCRUM_SUBMIT );
if( !is_null( $token_submit ) ) {
	$submit_value = unserialize( $token_submit );
}

# 如果提交的表单没有数据就使用token保存的数据,如果token里也没有就配置一个缺省数据
# 用户信息
if( gpc_isset( 'handler_id' ) ) {
	$submit_handler_id = gpc_get_string( 'handler_id', '' );
} else {
	if( array_key_exists( 'handler_id', $submit_value) ) {
		$submit_handler_id = $submit_value['handler_id'];
	}
}
# 开始日期
# FILTER_PROPERTY_START_YEAR FILTER_PROPERTY_START_MONTH FILTER_PROPERTY_START_DAY
$t_filter = filter_get_default();
if( gpc_isset( FILTER_PROPERTY_START_YEAR ) ) {
	$submit_start_year = gpc_get_string( FILTER_PROPERTY_START_YEAR, '' );
} else {
	if( array_key_exists( FILTER_PROPERTY_START_YEAR, $submit_value) ) {
		$submit_start_year = $submit_value[FILTER_PROPERTY_START_YEAR];
	}else{		
		$submit_start_year=$t_filter [FILTER_PROPERTY_START_YEAR];
	}
}
if( gpc_isset( FILTER_PROPERTY_START_MONTH ) ) {
	$submit_start_month = gpc_get_string( FILTER_PROPERTY_START_MONTH, '' );
} else {
	if( array_key_exists( FILTER_PROPERTY_START_MONTH, $submit_value) ) {
		$submit_start_month = $submit_value[FILTER_PROPERTY_START_MONTH];
	}else{
		$submit_start_month=$t_filter [FILTER_PROPERTY_START_MONTH];
	}
}
if( gpc_isset( FILTER_PROPERTY_START_DAY ) ) {
	$submit_start_day = gpc_get_string( FILTER_PROPERTY_START_DAY, '' );
} else {
	if( array_key_exists( FILTER_PROPERTY_START_DAY, $submit_value) ) {
		$submit_start_day = $submit_value[FILTER_PROPERTY_START_DAY];
	}else{
		$submit_start_day=$t_filter [FILTER_PROPERTY_START_DAY];
	}
}
# 结束日期
#FILTER_PROPERTY_END_YEAR  FILTER_PROPERTY_END_MONTH FILTER_PROPERTY_END_DAY
if( gpc_isset( FILTER_PROPERTY_END_YEAR ) ) {
	$submit_end_year = gpc_get_string( FILTER_PROPERTY_END_YEAR, '' );
} else {
	if( array_key_exists( FILTER_PROPERTY_END_YEAR, $submit_value) ) {
		$submit_end_year = $submit_value[FILTER_PROPERTY_END_YEAR];
	}else{
		$submit_end_year=$t_filter [FILTER_PROPERTY_END_YEAR];
	}
}
if( gpc_isset( FILTER_PROPERTY_END_MONTH ) ) {
	$submit_end_month = gpc_get_string( FILTER_PROPERTY_END_MONTH, '' );
} else {
	if( array_key_exists( FILTER_PROPERTY_END_MONTH, $submit_value) ) {
		$submit_end_month = $submit_value[FILTER_PROPERTY_END_MONTH];
	}else{
		$submit_end_month=$t_filter [FILTER_PROPERTY_END_MONTH];
	}
}
if( gpc_isset( FILTER_PROPERTY_END_DAY ) ) {
	$submit_end_day = gpc_get_string( FILTER_PROPERTY_END_DAY, '' );
} else {
	if( array_key_exists( FILTER_PROPERTY_END_DAY, $submit_value) ) {
		$submit_end_day = $submit_value[FILTER_PROPERTY_END_DAY];
	}else{
		$submit_end_day=$t_filter [FILTER_PROPERTY_END_DAY];
	}
}

# 把表单数据保存到token
$submit_value['handler_id'] = $submit_handler_id;

$submit_value[FILTER_PROPERTY_START_YEAR] = $submit_start_year;
$submit_value[FILTER_PROPERTY_START_MONTH] = $submit_start_month;
$submit_value[FILTER_PROPERTY_START_DAY] = $submit_start_day;

$submit_value[FILTER_PROPERTY_END_YEAR] = $submit_end_year;
$submit_value[FILTER_PROPERTY_END_MONTH] = $submit_end_month;
$submit_value[FILTER_PROPERTY_END_DAY] = $submit_end_day;
$t_res = token_set(
		ScrumPlugin::TOKEN_SCRUM_SUBMIT,
		serialize( $submit_value ),
		plugin_config_get( 'token_expiry' )
);


# Retrieve all statuses to display on the board
$statuses = array();
foreach( $columns as $col ) {
	$statuses = array_merge( $statuses, $col );
}

# 处理修改任务状态
if( gpc_isset( 'modifystatus_bug_id')&&gpc_isset( 'modifystatus_bug_status')){
	$submit_modifystatus_bug_id     = gpc_get_int( 'modifystatus_bug_id', 0 );
	$submit_modifystatus_bug_status = gpc_get_int( 'modifystatus_bug_status',0 );
	# 如果 modifystatus_bug_id modifystatus_bug_status 不为空就修改bug状态
	if($submit_modifystatus_bug_id>0 && $submit_modifystatus_bug_status>0){
		bug_set_field($submit_modifystatus_bug_id,"status",$submit_modifystatus_bug_status);
	}
}

$search_start_time =strtotime($submit_start_year. "-" .$submit_start_month. "-" .$submit_start_day." 00:00:00");
$search_end_time = strtotime($submit_end_year. "-" .$submit_end_month. "-" .($submit_end_day)." 00:00:00");

# Retrieve all bugs with the matching config filter and cache
$bug_table = db_get_table( 'mantis_bug_table' );
#检索出所有未解决的问题 status < 80
$query = "SELECT DISTINCT(a.id) FROM (SELECT id,handler_id,status,priority  FROM {$bug_table} WHERE (status < 80 ";

if( $current_project > 0 ) {
	$query .= " AND project_id IN (" . $current_project . ')';
}

if( $submit_handler_id > 0 ) {
	$query .= " AND handler_id =" . $submit_handler_id;
}

#加上检索出在搜索时间段内的已解决未关闭的问题 status = 80
$query .= ') OR (status = 80 ';
if( $current_project > 0 ) {
	$query .= " AND project_id IN (" . $current_project . ')';
}

if( $submit_handler_id > 0 ) {
	$query .= " AND handler_id =" . $submit_handler_id;
}
$query .= ' AND last_updated > '.$search_start_time;
$query .= ' AND last_updated < '.$search_end_time;
$query .= ' ) ';

#加上在搜索时间段内解决关闭的问题 （筛选加速条件选择的开始时间之后关闭的issue）  status = 90 
$query .= " UNION SELECT a.id,a.handler_id,a.status,a.priority  FROM {$bug_table}  a,mantis_bug_history_table b WHERE a.id = b.bug_id";
if( $current_project > 0 ) {
	$query .= " AND a.project_id IN (" . $current_project . ')';
}
if( $submit_handler_id > 0 ) {
	$query .= " AND a.handler_id =" . $submit_handler_id;
}
$query .= ' AND a.status = 90';
$query .= ' AND a.last_updated > '.$search_start_time;

$query .= ' AND b.field_name = "status" AND b.new_value = 80';
$query .= ' AND b.date_modified > '.$search_start_time;
$query .= ' AND b.date_modified < '.$search_end_time;

$query .= ') AS a ORDER BY a.handler_id DESC,a.status ASC, a.priority DESC, a.id DESC';

$result = db_query_bound($query);

$bug_ids = array();
while( $row = db_fetch_array( $result ) ) {
	$bug_ids[] = $row['id'];
}


# 对查询的数据进行整理
$bugs = array();//根据bug的不同状态进行分类存放
$clean_bug_ids = array();//整理后的id array
$resolved_count = 0; #已解决的问题计数

foreach( $bug_ids as $bug_id ) {
	$bug = bug_get( $bug_id,true);
	if( $bug->status >= $resolved_threshold ) { //对已解决的问题做检索时间过滤
			$resolved_count++; //对已解决以上的问题,进行数据统计
	}
	$clean_bug_ids[] =  $bug_id;
	$bugs[$bug->handler_id][$bug->status][] = $bug;

}
bug_cache_array_rows( $clean_bug_ids ); //缓存


# 统计数据
$bug_count = count( $clean_bug_ids ); //bug总数
if( $bug_count > 0 ) {
	$resolved_percent = floor( 100 * $resolved_count / $bug_count ); //已处理问题百分比
} else {
	$resolved_percent = 0;
}

#取定义的预估工时字段 如果小于等于0就是没有定义
$estimate_field_id = custom_field_get_id_from_name('Estimate_WH');

html_page_top( plugin_lang_get( 'board' ) );
?>

<link rel="stylesheet" type="text/css"
	href="plugins/Scrum/files/scrumboard.css" />
<br />
<table class="width100 scrumboard" align="center" cellspacing="1">

	<!-- Scrum Board Title and filter -->
	<tr>
		<form id="form1" action="<?php echo plugin_page( 'board' ) ?>" method="get">
			<input type="hidden" name="page" value="Scrum/board" />

			<input type="hidden" name="modifystatus_bug_id" value="" />
			<input type="hidden" name="modifystatus_bug_status" value="" />
			
			<td class="form-title" colspan="<?php echo count( $columns )+1 ?>">
 
				<?php echo plugin_lang_get( 'board' ) ?>			
				<!-- 显示出全部的用户名 --> &nbsp;&nbsp;&nbsp;&nbsp;用户列表: <select
				<?php echo helper_get_tab_index() ?> name="handler_id">
					<option value="0" selected="selected">ALL</option>
					<?php print_assign_to_option_list( $submit_handler_id )?>
				</select> <!-- 默认显示本周的日期 -->
				&nbsp;&nbsp;&nbsp;&nbsp;Start Date
				<?php
				$t_filter = filter_get_default();
				$t_chars = preg_split ( '//', config_get ( 'short_date_format' ), - 1, PREG_SPLIT_NO_EMPTY );
				foreach ( $t_chars as $t_char ) {
					if (strcasecmp ( $t_char, "M" ) == 0) {
						echo '<select name="', FILTER_PROPERTY_START_MONTH, '"', $t_menu_disabled, '>';
						print_month_option_list ( $submit_start_month );
						print "</select>\n";
					}
					if (strcasecmp ( $t_char, "D" ) == 0) {
						echo '<select name="', FILTER_PROPERTY_START_DAY, '"', $t_menu_disabled, '>';
						print_day_option_list ( $submit_start_day );
						print "</select>\n";
					}
					if (strcasecmp ( $t_char, "Y" ) == 0) {
						echo '<select name="', FILTER_PROPERTY_START_YEAR, '"', $t_menu_disabled, '>';
						#print_year_option_list ( $t_filter [FILTER_PROPERTY_START_YEAR] );
						print_year_range_option_list($submit_start_year,date( "Y" )-1,date( "Y" )+1);
						print "</select>\n";
					}
				}
				?>

				<!-- 默认显示本周的日期 -->
				&nbsp;&nbsp;&nbsp;&nbsp;End Date
				<?php
				$t_chars = preg_split ( '//', config_get ( 'short_date_format' ), - 1, PREG_SPLIT_NO_EMPTY );
				foreach ( $t_chars as $t_char ) {
					if (strcasecmp ( $t_char, "M" ) == 0) {
						echo '<select name="', FILTER_PROPERTY_END_MONTH, '"', $t_menu_disabled, '>';
						print_month_option_list ( $submit_end_month );
						print "</select>\n";
					}
					if (strcasecmp ( $t_char, "D" ) == 0) {
						echo '<select name="', FILTER_PROPERTY_END_DAY, '"', $t_menu_disabled, '>';
						print_day_option_list ( $submit_end_day );
						print "</select>\n";
					}
					if (strcasecmp ( $t_char, "Y" ) == 0) {
						echo '<select name="', FILTER_PROPERTY_END_YEAR, '"', $t_menu_disabled, '>';
						print_year_range_option_list( $submit_end_year,date( "Y" )-1,date( "Y" )+1);
						print "</select>\n";
					}
				}
				?>
				&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" value="查询" />
			</td>
		</form>
	</tr>

	<!-- Progress bars -->
	<tr>
		<td colspan="<?php echo count( $columns )+1 ?>">
			<div id="resolved_percent" class="scrumbar">
				<span class="bar" style="width: <?php echo $resolved_percent ?>%"><?php echo "{$resolved_count}/{$bug_count} ({$resolved_percent}%)" ?></span>
			</div>
		</td>
	</tr>

	<!-- Scrum Board Title-->
	<tr class="row-category">
		<td>开发者</td>
		<?php foreach( $columns as $column => $statuses ): ?>
		<td><?php echo plugin_lang_get( 'column_' . $column ); ?></td>
		<?php endforeach ?>
	</tr>

	<!-- Scrum Board Content-->
<?php foreach ( $bugs as $handle =>$handleissue ){?>	
	<tr class="row-1">
		<td class="scrumcolumn" width="10%">
		<?php echo $handle > 0 ? user_get_name ( $handle ) : '未指定';
		$new_estimate_wh =0;
		$confirmed_estimate_wh =0;
		$resolved_estimate_wh =0;
		if($estimate_field_id>0){ //如果定义的有预估工时字段，就取预估工时值
			foreach( $columns["new"] as $status1 ){
				if (isset ( $handleissue[$status1] )) {
					foreach ( $handleissue[$status1] as $bug ){
						$new_estimate_wh =$new_estimate_wh+get_due_date($bug->id, $estimate_field_id);
					}
				}				
			}
			foreach( $columns["confirmed"] as $status1 ){
				if (isset ( $handleissue[$status1] )) {
					foreach ( $handleissue[$status1] as $bug ){
						$confirmed_estimate_wh =$confirmed_estimate_wh+get_due_date($bug->id, $estimate_field_id);
					}
				}
			}
			foreach( $columns["resolved"] as $status1 ){
				if (isset ( $handleissue[$status1] )) {
					foreach ( $handleissue[$status1] as $bug ){
						$resolved_estimate_wh =$resolved_estimate_wh+get_due_date($bug->id, $estimate_field_id);
					}
				}
			}
			
		}
		?>
		<br/>
		未解决：<?php echo $new_estimate_wh;?>
		<br/>
		进行中：<?php echo $confirmed_estimate_wh;?>
		<br/>
		已完成：		
		<span <?php echo $resolved_estimate_wh<40 ? "style='background:#ff0000'":" " ?> title="已完成的预估工时">
		<?php echo $resolved_estimate_wh;?>
		</span>
		</td>	
<?php
	foreach( $columns as $column => $statuses ) {//显示三列 未解决 进行中 已解决
?>
		<td class="scrumcolumn" width="30%">
<?php

		foreach( $statuses as $status ) { // 显示每列里面 根据状态列出所有的issue
		if (isset ( $handleissue[$status] )) {
			foreach ( $handleissue[$status] as $bug ) { // 显示出一类状态的所有issue
				$sevcolor = array_key_exists( $bug->severity, $sevcolors )
				? $sevcolors[$bug->severity]
				: 'white' ;
				?>

		<div class="scrumblock">
				<p class="priority">
					<span style="background: <?php echo $sevcolor ?>">
				<?php print_status_icon( $bug->priority ); 
				$due_date = get_due_date($bug->id, $bug->due_date);
				?>
				</span>
				
				<?php
				if($estimate_field_id>0){ //如果定义的有预估工时字段，就取预估工时值
					$estimate = get_due_date($bug->id, $estimate_field_id);
				}
				
				if($bug->status < 80){
					$due_date_color = "c9effe";
					if ($bug->status < 80) {
						if ($due_date < ($current_date + 259200) && $due_date > $current_date) { // 三天内黄色
							$due_date_color = "ffddde";
						} elseif ($due_date < ($current_date + 1)) { // 超过今天红色
							$due_date_color = "ff0000";
						}
					}					
					?>
					<span class="duedate" style="background:#<?php echo $due_date_color;?>" title="Due_Date">
					<?php 				
					echo date( config_get( 'short_date_format' ), $due_date);?>
					</span> 
					<!-- 
					<span class="modifyhandle" title="修改任务执行人"> <a
							href="javascript:clickmodiowner('25595','81');"> <img
								src="plugins/Scrum/files/user.png" /></a>
					</span>
					-->
					
					<?php if($bug->status == 10 || $bug->status == 50){?>
					<span class="modifystatus" title="修改任务状态"> <a
							href="javascript:clickmodistatus('<?php echo $bug->id; ?>','40','<?php echo $estimate;?>');"> 
							<img src="plugins/Scrum/files/bullet_go.png" /></a>
					</span>	
					<?php }else {?>
					<span class="modifystatus" title="修改任务状态"> <a
							href="javascript:clickmodistatus('<?php echo $bug->id; ?>','50','<?php echo $estimate;?>');"> 
							<img src="plugins/Scrum/files/bullet_end.png" /></a>
					</span>	
					<?php }?>
				<?php }?>
				
				
				<?php 
				if($bug->status > 70){
					$resolve_color = "c9effe";
					$redate = 0;
					$redate = history_get_date($bug->id,80);
					if ($redate > ($current_date - 86400) and $redate < $current_date) { // 昨天处理的是红色
						$resolve_color = "ff0000";
					}				
						
					
				?>
				<span class="resolvedate" style="background:#<?php echo $resolve_color;?>" title="最后解决日期">
				<?php 
				echo $redate < 100 ? "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;":date( config_get( 'short_date_format' ), $redate);?>
				</span>
				<?php }?>
				
				
				<?php
				if($estimate_field_id>0){
				?>
				<span class="estimate" title="预估工时"> 
					<?php echo $estimate;?>
				</span>
				<?php }?>


				</p>
				

				<p class="summary">
				<?php
				echo string_get_bug_view_link ( $bug->id ) . ': ' . bug_format_summary ( $bug->id, 0 );
				?>
				<span class="project">[
				<?php
				if ($bug->project_id != $current_project) {
					$project_name = project_get_name ( $bug->project_id );
					echo $project_name . '- ';
				}
				echo category_full_name ( $bug->category_id, false )
				?>
				]</span>
				</p>
			</div>
<?php
			} // foreach
		} // if
	} # foreach
?>
		</td>
<?php
	} # foreach $statuses //显示三列 未解决 进行中 已解决
?>

	</tr>
<?php
	} # foreach $handleissue
?>
</table>
<script type="text/javascript">
function clickmodistatus(bug_id,status,whs_flag)  {
	if(whs_flag>24 || whs_flag==0)
	{
		alert("该issue 预估工时必须大于0且小于等于24小时");
	}
	else
	{
		var myForm = document.getElementById("form1");
		myForm.modifystatus_bug_id.value =bug_id;
		myForm.modifystatus_bug_status.value =status;
		myForm.submit();
	}
}

function clickmodiowner(bug_id,usercache) 
{ 
	URL="/modiowner.php?id="+bug_id+"&usercache="+usercache;
	window.open (URL, 'newwindow', 'height=180, width=400, toolbar =no, menubar=no, scrollbars=no, resizable=no, location=no, status=no')
	//window.open ("test.html", "newwindow", "height=300; width=400; toolbar =no; menubar=no; scrollbars=no; resizable=no; location=no; status=no")
} 


</script>
</br>
<?php
html_page_bottom();



