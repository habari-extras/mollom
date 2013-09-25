<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<title><?php _e('Verify your comment, '); ?><?php Options::out( 'title' ); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

	<link rel="stylesheet" href="<?php Site::out_url('habari'); ?>/3rdparty/blueprint/screen.css" type="text/css" media="screen">
	<link rel="stylesheet" href="<?php Site::out_url('habari'); ?>/3rdparty/blueprint/print.css" type="text/css" media="print">
	<link rel="stylesheet" href="<?php Site::out_url('admin_theme'); ?>/css/admin.css" type="text/css" media="screen">

	<script src="<?php Site::out_url('scripts'); ?>/jquery.js" type="text/javascript"></script>
	<script src="<?php Site::out_url('habari'); ?>/3rdparty/humanmsg/humanmsg.js" type="text/javascript"></script>
</head>
<body class="login">

	<div id="page">

		<h1><a href="<?php Site::out_url('habari'); ?>" title="<?php _e('Go to Site'); ?>"><?php Options::out('title'); ?></a></h1>

		<div class="container">
			<p><?php _e( 'Your comment did not pass our spam filter. Please enter the text you see on the box below to verify you are not a spam bot and your comment is valid. Thank You and sorry for the inconveneance', 'mollom' ); ?></p>

				<form method="post" action="<?php URL::out( 'mollom_fallback', array( 'fallback' => 'captcha' ) ); ?>">
					<p>
						<?php echo $theme->captcha['html']; ?><br />
						
						<?php echo $theme->audio_captcha['html']; ?> <br />
						
						<label><?php _e( 'Enter the text you see in the image above or press play to hear the text.', 'mollom' ); ?><br /> <input type="text" name="mollom_captcha" id="mollom_captcha" /></label>
					</p>
					<p><input type="submit" value="Submit" /></p>

				</form>

		</div>

	</div>

	<script type="text/javascript">
	jQuery(document).ready(function() {
		<?php echo Session::messages_out( true, array( 'Format', 'humane_messages' ) ); ?>
	})
  </script>

</body>
</html>
