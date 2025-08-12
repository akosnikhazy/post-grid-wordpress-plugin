<?php
/*
Plugin Name: Post Grid by Category
Description: Selected categories posts can be embeded as a grid in a page. Also you can exlude those from the home page.
Version: 1.0
Author: Ákos Nikházy
*/

if (!defined('ABSPATH')) {
    exit;
}

define('PN', array('« previous','next »'));

add_action('pre_get_posts', 'exclude_category_from_home');
add_shortcode('post_grid', 'display_post_grid');
add_action('admin_menu', 'post_grid_plugin_menu');
add_action('wp_enqueue_scripts', 'post_grid_styles');
add_action('admin_enqueue_scripts', 'post_grid_admin_styles');

function post_grid_plugin_menu() {
    add_menu_page(
        'Post Grid Settings', 
        'Post Grid Settings', 
        'manage_options', 
        'post-grid-settings',
        'post_grid_settings_page',
		'dashicons-grid-view'
    );
}

function post_grid_settings_page() {
   
    if (!current_user_can('manage_options'))return;
    
    if (isset($_POST['submit']))
	{ // save changes

		// exlude from home page or not
		$post_grid_exlude_or_not = false;
		
		// categories we use for the grid
        $post_grid_categories = array(); 
		
		// text of back and next buttons in pagination
		$post_grid_pagination_previous_text 	= PN[0];
		$post_grid_pagination_next_text 		= PN[1];
		
		
		if(isset($_POST['post_grid_exlude_or_not']))
			$post_grid_exlude_or_not = true;
		
		if(isset($_POST['post_grid_categories']))
			$post_grid_categories = $_POST['post_grid_categories'];
		
		if(isset($_POST['post_grid_pagination_previous_text']))
			$post_grid_pagination_previous_text = $_POST['post_grid_pagination_previous_text'];
		
		if(isset($_POST['post_grid_pagination_next_text']))
			$post_grid_pagination_next_text = $_POST['post_grid_pagination_next_text'];
		
		
        update_option('post_grid_categories', $post_grid_categories);
		update_option('post_grid_pagination_pn_text',array($post_grid_pagination_previous_text,$post_grid_pagination_next_text));
		update_option('post_grid_exlude_or_not',$post_grid_exlude_or_not);
		
	}

	$post_grid_exlude_or_not = get_option('post_grid_exlude_or_not',true);
    $post_grid_categories = get_option('post_grid_categories', array());
    $post_grid_pagination_pn_text =  get_option('post_grid_pagination_pn_text',PN);
	
	$all_categories = get_categories();
	
	// build the admin page
	$html = '<div class="wrap">
				<h1>Post Grid Settings</h1>
				
				<form method="post" action="">
					
					<p>Select the categories you want to see in the Post Grid.</p>';
					
	foreach ($all_categories as $category)
	{
		$html .= '<label><input type="checkbox" name="post_grid_categories[]" value="' . esc_attr($category->slug) . '" ' . checked(in_array($category->slug, $post_grid_categories),true,false) . '> ' . esc_html($category->name) . '</label><br>';
	}
   
    $html .= '  <hr><label>Exlude selected categories from home page post list? <input type="checkbox" name="post_grid_exlude_or_not" ' . checked($post_grid_exlude_or_not,true,false) . '></label><hr>
				<p>Pagination "previous" and "next" button text</a>
				<div id="pncontrols">
				<label for="previous-text">Previous</label> <input type="text" id="previous-text" name="post_grid_pagination_previous_text" value="' . $post_grid_pagination_pn_text[0] . '" placeholder="previous" required><br>
				<label for="next-text">Next</label> <input type="text" id="next-text" name="post_grid_pagination_next_text" value="' . $post_grid_pagination_pn_text[1] . '" placeholder="next" required><br>
				</div>
				<br><br>
				<input type="submit" name="submit" class="button button-primary" value="Save Changes">
				</form>
			
			<h2>How to use</h2>
			<p>Just put <code>[post_grid posts_per_page="10"]</code> in any post or page. It will display a grid of those posts in the selected categories while hiding them from the main page if that option is selected. Use the posts_per_page attribute to set how many posts you want to show. If there are more posts, a pager will appear. You can change the Previous and Next button text. It uses the excerpt for preview text in the grid.</p>
			
			<h2>Why?</h2>
			<p>I liked what Ross did on his <a href="https://www.accursedfarms.com/games/" target="_blank">Accursed Farms</a> website and I wanted to start my own "wanna play sometime" game list.</p>
			
			<h3>Legal</h3>
			<p>This plugin created by Ákos Nikházy. It is <a href="https://github.com/akosnikhazy/post-grid-wordpress-plugin" target="_blank">free and open source</a>. Do whatever.</p>
			</div>';
	
	echo $html;
	
}

function exclude_category_from_home($query) 
{
	
	if(!get_option('post_grid_exlude_or_not',true)) return;
	
	if ($query->is_home() && $query->is_main_query()) 
	{
        
        $post_grid_categories = get_option('post_grid_categories', array());
        
        $excluded_cat_ids = array();
		
        foreach ($post_grid_categories as $slug) 
		{
			
            $category = get_category_by_slug($slug);
			
            if ($category) 
			{
                $excluded_cat_ids[] = $category->term_id;
            }
			
        }
        
        if (!empty($excluded_cat_ids)) 
		{
            $query->set('category__not_in', $excluded_cat_ids);
        }
    }
}

function display_post_grid($atts) 
{
	
    $atts = shortcode_atts(array('posts_per_page' => 10), $atts, 'post_grid');
    
	$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
	
	$post_grid_categories = get_option('post_grid_categories', array());
    
	$excluded_cat_ids = array();
	
    foreach ($post_grid_categories as $slug) 
	{
        $category = get_category_by_slug($slug);
		
        if ($category) 
		{
            $excluded_cat_ids[] = $category->term_id;
        }
    }
	
	
	
    $args = array(
        'category__in' => $excluded_cat_ids, 
        'posts_per_page' => intval($atts['posts_per_page']),
        'paged' => $paged, 
    );
	
    $query = new WP_Query($args);
	
	// build the grid
    $html = '<div class="pg-plugin-post-grid">';

    if (!$query->have_posts())
	{ 
		 $html .= '</div>';
	}
	else
	{
        while ($query->have_posts()) 
		{
			
            $query->the_post();
            
			$html .= '<div class="pg-plugin-post">';
          
            if (has_post_thumbnail()) 
			{
                $html .= '<div class="pg-plugin-post-thumbnail"><a href="' . get_permalink() . '">' . get_the_post_thumbnail(get_the_ID(), 'medium') . '</a></div>';
            }
            
			$html .= '<h3 class="pg-plugin-post-title"><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            
			$html .= '<div class="pg-plugin-post-excerpt">' . get_the_excerpt() . '</div>';
         
            $html .= '</div>';
			
        }
		    
		$html .= '</div>';
        
        
		
        if ($query->max_num_pages > 1) 
		{
			$post_grid_pagination_pn_text =  get_option('post_grid_pagination_pn_text', PN);
			
            $html .= '<div class="pg-plugin-post-pagination">';
            $html .= paginate_links(array(
                'total' => $query->max_num_pages,
                'current' => $paged,
                'format' => '?paged=%#%',
                'prev_text' => __($post_grid_pagination_pn_text[0]),
                'next_text' => __($post_grid_pagination_pn_text[1]),
            ));
            $html .= '</div>';
        }
    }

    wp_reset_postdata();

    return $html;
}

function post_grid_styles() 
{
    wp_enqueue_style('games-grid-style', plugin_dir_url(__FILE__) . 'css/post-grid.css',array(),time());
}

function post_grid_admin_styles($hook) 
{

	if ($hook != 'toplevel_page_post-grid-settings') return;
	
	wp_enqueue_style('post-grid-admin-style', plugin_dir_url(__FILE__) . 'css/post-grid-admin.css', array(), time());
	
}
?>