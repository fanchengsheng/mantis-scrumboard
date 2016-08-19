<?php


/**
 * 返回指定bug的已解决日期   或bug的关闭日期
 * 80:resolved,90:closed
 * The array is indexed from 0 to N-1.  The second dimension is: 'date', 'userid', 'username',
 * 'field','type','old_value','new_value'
 * @param int $p_bug_id
 * @re
 */

function history_get_date( $p_bug_id, $p_new_value) {
	$t_mantis_bug_history_table = db_get_table( 'mantis_bug_history_table' );

	$query = "SELECT * 
	FROM $t_mantis_bug_history_table
	WHERE bug_id=" . $p_bug_id . " and new_value = ".$p_new_value."
	ORDER BY id ASC";
	$result = db_query_bound( $query);
	$raw_history_count = db_num_rows( $result );
	$raw_history = 0;

	for( $i = 0;$i < $raw_history_count;++$i ) {
		$t_row = db_fetch_array( $result );

		$raw_history = $t_row['date_modified'];		

	}

	return $raw_history;
}

/**
 * 返回指定bug的已解决日期   或bug的关闭日期
 * 80:resolved,90:closed
 * The array is indexed from 0 to N-1.  The second dimension is: 'date', 'userid', 'username',
 * 'field','type','old_value','new_value'
 * @param int $p_bug_id
 * @re
 */

function get_due_date( $p_bug_id, $p_field_id) {
	$t_mantis_bug_history_table = db_get_table( 'mantis_custom_field_string_table' );

	$query = "SELECT *
	FROM $t_mantis_bug_history_table
	WHERE bug_id=" . $p_bug_id . " and field_id = ".$p_field_id;
	$result = db_query_bound( $query);
	$raw_history_count = db_num_rows( $result );
	$raw_history = 0;

	for( $i = 0;$i < $raw_history_count;++$i ) {
		$t_row = db_fetch_array( $result );

		$raw_history = $t_row['value'];

	}

	return $raw_history;
}