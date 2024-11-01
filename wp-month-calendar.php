<?php
/*
Plugin Name: WP Month Calendar
Plugin URI: http://niklas-rother.de/
Description: Modified version of the original Calendar Widget build in to WP, this on displays a full year per page.
Version: 1.0
Author: Niklas Rother
Author URI: http://niklas-rother.de
Text Domain: wp-month-calendar
  
    Copyright 2010 Niklas Rother (e-mail: info@niklas-rother.de)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
function nr_get_month_calendar()
{
	global $wpdb, $m, $year, $wp_locale, $posts;
	$cache = array();
	$key = md5($m . $year);
	if ( $cache = wp_cache_get('nr_get_month_calendar', 'calendar'))
	{
		if (is_array($cache) && isset( $cache[$key])) //cache valid and contains current year
		{
			return $cache[$key];
		}
	}

	if (!is_array($cache))
		$cache = array();

	// Quick check. If we have no posts at all, abort!
	if (!$posts)
	{
		$gotsome = $wpdb->get_var("SELECT 1 as test FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' LIMIT 1");
		if (!$gotsome)
		{
			$cache[$key] = '';
			wp_cache_set('nr_get_month_calendar', $cache, 'calendar');
			return;
		}
	}

	if (isset($_GET['w']))
		$w = ''.intval($_GET['w']);

	// Let's figure out when we are
	if (!empty($year))
	{
		$thisyear = ''.intval($year);
	}
	elseif (!empty($w))
	{
		$thisyear = ''.intval(substr($m, 0, 4));
	}
	elseif (!empty($m))
	{
		$thisyear = ''.intval(substr($m, 0, 4));
	}
	else
	{
		$thisyear = gmdate('Y', current_time('timestamp'));
	}

	// Get the next and previous year with at least one post
	$previous = $wpdb->get_row("SELECT DISTINCT YEAR(post_date) AS year
		FROM $wpdb->posts
		WHERE post_date < '$thisyear-01-01'
		AND post_type = 'post' AND post_status = 'publish'
			ORDER BY post_date DESC
			LIMIT 1");
	$next = $wpdb->get_row("SELECT	DISTINCT YEAR(post_date) AS year
		FROM $wpdb->posts
		WHERE post_date > '$thisyear-31-12'
		AND post_type = 'post' AND post_status = 'publish'
			ORDER	BY post_date ASC
			LIMIT 1");
			
	$calendar_output = '<table id="wp-calendar" summary="' . esc_attr__('Calendar') . '">
	<caption>' . $thisyear . '</caption>

	<tfoot>
	<tr>';

	if ($previous)
	{
		$calendar_output .= "\n\t\t".'<td colspan="2" id="prev"><a href="' . get_year_link($previous->year) . '" title="' . esc_attr(sprintf(__('View posts for %1$s'),  $previous->year)) . '">&laquo; ' . $previous->year . '</a></td>';
	}
	else
	{
		$calendar_output .= "\n\t\t".'<td colspan="2" id="prev" class="pad">&nbsp;</td>';
	}


	if ($next)
	{
		$calendar_output .= "\n\t\t".'<td colspan="2" id="next"><a href="' . get_year_link($next->year) . '" title="' . esc_attr(sprintf(__('View posts for %1$s'), $next->year)) . '">' . $next->year . ' &raquo;</a></td>';
	}
	else
	{
		$calendar_output .= "\n\t\t".'<td colspan="2" id="next" class="pad">&nbsp;</td>';
	}

	$calendar_output .= '
	</tr>
	</tfoot>

	<tbody>
	<tr>';

	// Get month with posts
	$month_with_posts = $wpdb->get_results("SELECT DISTINCT MONTH(post_date)
		FROM $wpdb->posts WHERE YEAR(post_date) = '$thisyear'
		AND post_type = 'post' AND post_status = 'publish'
		AND post_date < '" . current_time('mysql') . '\'', ARRAY_N);
	if ($month_with_posts)
	{
		foreach ((array) $month_with_posts as $month_with ) {
			$post_month[] = $month_with[0]; //collum 0
		}
	} else {
		$post_month = array();
	}

	if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'camino') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'safari') !== false)
		$ak_title_separator = "\n";
	else
		$ak_title_separator = ', ';

	$ak_titles_for_month = array();
	$ak_post_titles = $wpdb->get_results("SELECT ID, post_title, MONTH(post_date) as month "
		."FROM $wpdb->posts "
		."WHERE YEAR(post_date) = '$thisyear' "
		."AND post_date < '".current_time('mysql')."' "
		."AND post_type = 'post' AND post_status = 'publish'"
	);
	if ($ak_post_titles)
	{
		foreach ((array) $ak_post_titles as $ak_post_title)
		{
				$post_title = esc_attr(apply_filters( 'the_title', $ak_post_title->post_title, $ak_post_title->ID ));

				if (empty($ak_titles_for_month['month_'.$ak_post_title->month]))
					$ak_titles_for_month['month_'.$ak_post_title->month] = '';
				if (empty($ak_titles_for_month["$ak_post_title->month"]) ) //first one
					$ak_titles_for_month["$ak_post_title->month"] = $post_title;
				else
					$ak_titles_for_month["$ak_post_title->month"] .= $ak_title_separator . $post_title;
		}
	}

	//write the month table
	for ($month = 1; $month <= 12; ++$month)
	{
		if (isset($newrow) && $newrow)
			$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
		$newrow = false;

		if ($month == gmdate('m', current_time('timestamp')) && $thisyear == gmdate('Y', current_time('timestamp')) )
			$calendar_output .= '<td id="today" style="width:25%;">'; //mark this month
		else
			$calendar_output .= '<td style="width:25%;">'; //without the style attrib, the calendar looks wired...

		if (in_array($month, $post_month)) //any posts this month?
				$calendar_output .= '<a href="' . get_month_link($thisyear, $month) . '" title="' . esc_attr($ak_titles_for_month[$month]) . '">' . $wp_locale->get_month_abbrev($wp_locale->get_month($month)) . '</a>';
		else
			$calendar_output .= $wp_locale->get_month_abbrev($wp_locale->get_month($month));
		$calendar_output .= '</td>';

		if ($month % 4 == 0)
			$newrow = true;
	}

	$calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";

	$cache[$key] = $calendar_output;
	wp_cache_set('nr_get_month_calendar', $cache, 'calendar');
	
	return $calendar_output;
}

class NR_Widget_Month_Calendar extends WP_Widget
{
	function NR_Widget_Month_Calendar()
	{
		$widget_ops = array('classname' => 'widget_wp_month_calendar', 'description' => __('A month calendar of your site&#8217;s posts'));
		$this->WP_Widget('wp_month_calendar', __('Month Calendar'), $widget_ops);
	}

	function widget($args, $instance)
	{
		extract($args);
		$title = apply_filters('widget_title', empty($instance['title']) ? '&nbsp;' : $instance['title'], $instance, $this->id_base);
		echo $before_widget;
		if ($title)
			echo $before_title . $title . $after_title;
		echo '<div id="calendar_wrap">';
		echo nr_get_month_calendar();
		echo '</div>';
		echo $after_widget;
	}

	function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	function form($instance)
	{
		$instance = wp_parse_args((array) $instance, array( 'title' => '' ) );
		$title = strip_tags($instance['title']);
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
<?php
	}
}

function nr_widget_init_action()
{
	register_widget('NR_Widget_Month_Calendar');
}
function nr_calendar_cache() {
	wp_cache_delete( 'nr_get_month_calendar', 'calendar' );
}

add_action( 'save_post', 'nr_calendar_cache' );
add_action( 'delete_post', 'nr_calendar_cache' );
add_action( 'update_option_start_of_week', 'nr_calendar_cache' );
add_action( 'update_option_gmt_offset', 'nr_calendar_cache' );
add_action('widgets_init', 'nr_widget_init_action');