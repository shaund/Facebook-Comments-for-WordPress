<?php
	/**********************************
	 Facebook comments box inclusion
	 **********************************/

	// Insert Facebook comments manually or into the comments array
	function facebook_comments($comments='') {
		global $fbc_options, $wp_query;

		// Return out of function if commenting is closed for this post
	    if (!comments_open()) {
                if ($fbc_options['hideClosed'] != 1)
			echo "<h2>Comments Closed</h2>";
	    	return $comments;
	    }

	    // Return out of function if we're only supposed to display comments on pages OR posts
	    if (($fbc_options['displayPagesOrPosts'] == 'pages') && (!is_page())) {
	    	return $comments;
	    }

	    if (($fbc_options['displayPagesOrPosts'] == 'posts') && (!is_single())) {
	    	return $comments;
	    }

		$postId = $wp_query->post->ID;
	    $xid = $fbc_options['xid'] . "_post$postId";
	    $postTitle = get_the_title($postId);
	    $postUrl = get_permalink($postId);

	    // Decide which stylesheet to use
	    $customStylesheet = fbComments_getStylesheet();
		
		// if(empty($fbc_options['appId'])) $fbc_options = get_option('fbComments'); 	
		// if(empty($fbc_options['appSecret'])) $fbc_options = get_option('fbComments'); 
		// if(empty($fbc_options['appId'])) $fbc_options['appId'] = get_option('fbComments_appId');
		// if(empty($fbc_options['appSecret'])) $fbc_options['appSecret'] = get_option('fbComments_appSecret');
		
		
		
		// Only insert the Facebook comments if both an application ID and an application secret has been set
		if (!empty($fbc_options['appId']) && !empty($fbc_options['appSecret'])) {
			// Store our access token if it hasn't already been saved
			fbComments_storeAccessToken();

			echo "\n<!-- Facebook Comments for WordPress v" . FBCOMMENTS_VER . " by " . FBCOMMENTS_AUTHOR . " (" . FBCOMMENTS_WEBPAGE . ") -->\n
<a name='facebook-comments'></a>\n";

	    	if ($fbc_options['includeFbJs']) {
	    		fbComments_includeFbJs();
			}

			// Print out the JavaScript that will catch new comments in order to update comment counts and send notifications
			fbComments_printCommentCatchAndNotificationScripts($xid, $postTitle, $postUrl);

		if ($fbc_options['includeHiddenSEOComments'] == 1) {
			// add special style info for hiding SEO comment box "behind" current box
			echo "\n<div id='fbComments' style='position: relative; z-index: 0; {$fbc_options['containerCss']}'>\n";
		} else { 
        		echo "\n<div id='fbComments' style='{$fbc_options['containerCss']}'>\n";
		}

        	if ($fbc_options['displayTitle']) {
        		echo "\t<p style='{$fbc_options['titleCss']}'>" . __($fbc_options['title']) . "</p>\n";
        	}

    		// Print out the JavaScript for calculating the width of the comments box
    		fbComments_printFbCommentsTag($xid, $postTitle, $postUrl, $customStylesheet);

			// Hide the WordPress commenting form if requested
			if ($fbc_options['hideWpComments']) {
			    return array(); // Must return an empty array so foreach() loops in certain themes don't produce errors
			}
		} else { // If no application ID or application secret are set, display a message asking the user to set one (if they have permission to do so)
			fbComments_handleNoAppId();
		}

		return $comments;
	}

	/**********************************
	 Facebook comments box helpers
	 **********************************/

	function fbComments_getStylesheet() {
		global $fbc_options;

		if (($fbc_options['hideFbLikeButton']) && ($fbc_options['darkSite'])) {
	    	return FBCOMMENTS_CSS_HIDELIKEANDDARKSITE . '?' . fbComments_getRandXid();
	    } elseif ($fbc_options['hideFbLikeButton']) {
	    	return FBCOMMENTS_CSS_HIDELIKE . '?' . fbComments_getRandXid();
	    } elseif ($fbc_options['darkSite']) {
	    	return FBCOMMENTS_CSS_DARKSITE . '?' . fbComments_getRandXid();
	    } else {
	    	return FBCOMMENTS_CSS_HIDEFBLINK . '?' . fbComments_getRandXid();
	    }
	}

	function fbComments_handleNoAppId() {
		global $fbc_options;

		get_currentuserinfo(); // Get user info to see if the currently logged in user (if any) has the 'manage_options' capability

		if (current_user_can('manage_options')) {
		    $fbc_optionsPage = admin_url('options-general.php?page=facebook-comments');

		    echo "\n<!-- Facebook Comments for WordPress v" . FBCOMMENTS_VER . " by " . FBCOMMENTS_AUTHOR . " (" . FBCOMMENTS_WEBPAGE . ") -->

<div style='{$fbc_options['containerCss']}'>\n";

			if ($fbc_options['displayTitle']) {
				echo "\t<p style='{$fbc_options['titleCss']}'>" . __($fbc_options['title']) . "</p>\n";
			}

			echo "\t<div style='background-color: #ffebe8; border: 1px solid #c00; padding: 7px; font-weight: bold; border-radius: 5px; -moz-border-radius: 5px; -webkit-border-radius: 5px; -khtml-border-radius: 5px;'>" . __("Your Facebook comments would normally appear here, but you haven't set a valid application ID or application secret yet. <a href='$fbc_optionsPage' style='color: #c00;'>Go set them now</a>.") . "</div>
</div>\n";
		}
	}

	function fbComments_includeFbJs() {
		global $fbc_options;

		// Decide whether or not to include the Facebook JavaScript library the old way or the new way
	    if ($fbc_options['includeFbJsOldWay']) {
	    	echo "\n<div id='fb-root'></div>
<script type='text/javascript'>
    window.fbAsyncInit = function() {
    	FB.init({
    		appId: '{$fbc_options['appId']}',
    		status: true,
    		cookie: true,
    		xfbml: true
    	});
    };

    (function() {
    	var e = document.createElement('script'); e.async = true;
    	e.src = document.location.protocol + '//connect.facebook.net/{$fbc_options['language']}/all.js';
    	document.getElementById('fb-root').appendChild(e);
    }());
</script>\n";
	    } else {
	    	echo "\n<div id='fb-root'></div>
<script src='http://connect.facebook.net/{$fbc_options['language']}/all.js#appId={$fbc_options['appId']}&amp;xfbml=1' type='text/javascript'></script>\n";
		}
	}

	function fbComments_printCommentCatchAndNotificationScripts($xid, $postTitle, $postUrl) {
		global $fbc_options;

		echo "
<script type='text/javascript'>
	var addedComment = function(response) {
		//console.log('fbComments: Caught added comment');
		//console.log('fbComments:     Making AJAX call to update Facebook comment count');
		$.post('" . FBCOMMENTS_PATH . "facebook-comments-ajax.php', { fn: 'addComment', xid: '$xid' }, function(resp) {
			if (resp === 'true') {
				//console.log('fbComments:     Updated and cached Facebook comment count for post with xid=$xid');
			} else {
				//console.log('fbComments:     FAILED to update Facebook comment count for post with xid=$xid');
			}
		});\n";

		if ($fbc_options['notify']) {
			echo "
		//console.log('fbComments:     Making AJAX call to send email notification');
		$.post('" . FBCOMMENTS_PATH . "facebook-comments-ajax.php', { fn: 'sendNotification', xid: '$xid', postTitle: '$postTitle', postUrl: '$postUrl' }, function(resp) {
			if (resp === 'true') {
				//console.log('fbComments:     Sent email notification');
			} else {
				//console.log('fbComments:     FAILED to send email notification');
			}
		});";
		}

		echo "
	};

	// Facebook has changed the event name
	FB.Event.subscribe('comment.create', addedComment);
</script>\n";
	}

	function fbComments_printFbCommentsTag($xid, $postTitle, $postUrl, $customStylesheet) {
		global $fbc_options;
		// Since the 'publish_feed' option defaults to true, we need to pass it an explicit false if it's turned off
		$publishToWall = ($fbc_options['publishToWall']) ? 'true' : 'false';
		// if ($fbc_options['newUser'] == 1) {
			// echo "\t<fb:comments href='$postUrl' ",
				// "numposts='{$fbc_options['numPosts']}' ",
				// "width='{$fbc_options['width']}' ",
				// "publish_feed='$publishToWall' ",
				// "</fb:comments>";
		// }
		echo "commentVersion: {$fbc_options['commentVersion']}";
		if ($fbc_options['v1plusv2'] == 1) {
			$fbc_options['hideFbLikeButton'] = true;
			update_option('fbComments', $fbc_options);
			echo "\t<fb:comments xid='$xid' href='$postUrl' ",
				"numposts='{$fbc_options['numPosts']}' ",
				"width='{$fbc_options['width']}' ",
				"publish_feed='$publishToWall' ",
				"migrated='1'></fb:comments>";
				
			echo "\t<fb:comments xid='$xid' ",
				"numposts='{$fbc_options['numPosts']}' ",
				"width='{$fbc_options['width']}' ",
				"simple='{$fbc_options['noBox']}' ",
				"publish_feed='$publishToWall' ",
				"reverse='{$fbc_options['reverseOrder']}' ",
				"css='$customStylesheet' ",
				"title='$postTitle' ",
				"url='$postUrl' ",
				"notify='true'></fb:comments>";
				
		} else if ($fbc_options['commentVersion'] == 'v2migrated') {
			// $xid = urlencode($xid);
			echo "\t<fb:comments xid='$xid' ",
				"numposts='{$fbc_options['numPosts']}' ",
				"width='{$fbc_options['width']}' ",
				"publish_feed='$publishToWall' ",
				"migrated='1'></fb:comments>";
				
		} else if ($fbc_options['commentVersion'] == 'v2') {
			echo "\t<fb:comments xid='$xid' href='$postUrl' ",
				"numposts='{$fbc_options['numPosts']}' ",
				"width='{$fbc_options['width']}' ",
				"publish_feed='$publishToWall' ",
				"migrated='1'></fb:comments>";
				
		} else if ($fbc_options['commentVersion'] == 'v1') {
			echo "\t<fb:comments xid='$xid' ",
				"numposts='{$fbc_options['numPosts']}' ",
				"width='{$fbc_options['width']}' ",
				"simple='{$fbc_options['noBox']}' ",
				"publish_feed='$publishToWall' ",
				"reverse='{$fbc_options['reverseOrder']}' ",
				"css='$customStylesheet' ",
				"title='$postTitle' ",
				"url='$postUrl' ",
				"notify='true'></fb:comments>";
		}
		// Add the SEO comment box (if necessary)
		fbc_facebook_seo_comments($xid);
		echo "</div>\n";
	}
	
	
	/**
	 * Outputs comments in a hidden, SEO friendly, container
	 * 
	 */
	function fbc_facebook_seo_comments($xid) {
		global $fbc_options;
		
		// check to see if we want to show hidden comments
		if ($fbc_options['includeHiddenSEOComments'] == 1) {
		
			echo '<div style="display: block; position: absolute; left: 0; top: 0; z-index: -9; height: 1px; width: 1px; overflow: hidden; ">'."\n";
	
			// retrieve all top-level comments for this blog
			$query = "select post_fbid, fromid, object_id, text, time, reply_xid from comment where xid='$xid'";
			
			// retrieve our comments
			$query_url = "https://api.facebook.com/method/fql.query?format=json&query=".urlencode($query)."&access_token=".$fbc_options['accessToken'];
			$content = file_get_contents($query_url);
			
			// decode JSON
			$comments = json_decode($content);
			
			// hash array to avoid looking up same user multiple times
			$users = array();
			
			// if we have comments start outputting them
			if (!empty($comments)) {
				print '<ul>'."\n";
				// loop through each comment
				foreach ($comments as $comment) {
			
					//avoid excessive user look ups by storing them in hash array
					if (!isset($users[$comment->fromid])) {
						// retrieve user
						$user_url = "https://graph.facebook.com/".$comment->fromid;
						$user = json_decode(file_get_contents($user_url));
						$users[$comment->fromid] = $user;
					} else {
						$user = $users[$comment->fromid];
					}
			
					// output comment and user
					print '<li><a href="'.$user->link.'"><img src="https://graph.facebook.com/'.$user->id.'/picture" alt="'.$user->first_name . ' ' . $user->last_name .'" /></a><br />'."\n";
					print '<strong><a href="'.$user->link.'">'.$user->first_name . ' ' . $user->last_name .'</a></strong>: '."\n";
					print '<p>'.make_clickable($comment->text).'</p>';
					print '<span class="date">'.date("M j \a\\t g:ia", $comment->time).'</span>';
			
					// check for replies
					$reply_query = "select post_fbid, fromid, object_id, text, time, reply_xid from comment where object_id='$comment->post_fbid'";
					$content = file_get_contents($query_url);
					$replies = json_decode($content);
					// if it has replies output them
					if (!empty($replies)) {
						print '<ul>';
						// loop through each reply
						foreach ( $replies as $reply ) {
							//avoid excessive user look ups by storing them in hash array
							if (!isset($users[$reply->fromid])) {
								$user_url = "https://graph.facebook.com/".$reply->fromid;
								$user = json_decode(file_get_contents($user_url));
								$users[$reply->fromid] = $user;
							} else {
								$user = $users[$reply->fromid];
							}
							// output the replies
							print '<li><a href="'.$user->link.'"><img src="https://graph.facebook.com/'.$user->id.'/picture" alt="'.$user->first_name . ' ' . $user->last_name .'" /></a><br />'."\n";
							print '<strong><a href="'.$user->link.'">'.$user->first_name . ' ' . $user->last_name .'</a></strong>:'."\n";
							print '<p>'.make_clickable($reply->text).'</p>';
							print '<span class="date">'.date("M j \a\\t g:ia", $reply->time).'</span>';
							print '</li>'."\n\n";
						}
						print '</ul>'."\n";
					}
			
					print '</li>'."\n\n\n";
				}
				print '</ul>'."\n\n";
			}
			
			echo '</div>'."\n";		
		}
	}	
	
	
	