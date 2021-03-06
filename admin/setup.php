<?
require_once '_base.php';

# do not render this page unless there is no repo
if ($integrity !== 2) {
	header("Location: ".gb::$site_url."gitblog/admin/");
	exit(0);
}

# Got POST
if (isset($_POST['submit'])) {
	# -------------------------------------------------------------------------
	# check input	
	if (!trim($_POST['email']) || strpos($_POST['email'], '@') === false) {
		$errors[] = '<b>Missing email.</b>
			Please supply a valid email address to be used for the administrator account.';
	}
	if (!trim($_POST['passphrase'])) {
		$errors[] = '<b>Empty pass phrase.</b>
			The pass phrase is empty or contains only spaces.';
	}
	if ($_POST['passphrase'] !== $_POST['passphrase2']) {
		$errors[] = '<b>Pass phrases not matching.</b>
			You need to type in the same pass phrase in the two input fields below.';
	}
	
	# -------------------------------------------------------------------------
	# create gb-config.php
	if (!$errors) {
		$config_path = gb::$site_dir."/gb-config.php";
		$s = file_get_contents(gb::$dir.'/skeleton/gb-config.php');
		# title
		$s = preg_replace('/(gb::\$site_title[\t ]*=[\t ]*)\'[^\']*\';/', 
			'${1}'.var_export($_POST['title'],1).";", $s, 1);
		# secret
		$secret = '';
		while (strlen($secret) < 62) {
			mt_srand();
			$secret .= base_convert(mt_rand(), 10, 36);
		}
		$s = preg_replace('/(gb::\$secret[\t ]*=[\t ]*)\'\';/',
			'${1}'.var_export($secret,1).";", $s, 1);
		#header('content-type: text/plain; charset=utf-8');var_dump($s);exit(0);
		file_put_contents($config_path, $s);
		chmod($config_path, 0660);
		# reload config
		require $config_path;
	}
	
	# -------------------------------------------------------------------------
	# Can git be found and if so, what version?
	try {
		$version = array_pop(explode(' ', trim(gb::exec("--version"))));
		$version = array_map('intval', explode('.', $version));
		if ($version[0] < 1 || $version[1] < 6) {
			$errors[] = '<b>To old git version.</b> Gitblog requires git version 1.6 
				or newer. Please upgrade your git. ('.h(`which git`).')';
		}
	}
	catch (GitError $e) {
		$errors[] = '<b>git not found in $PATH</b><br/><br/>
			
			If git is not installed, please install it. Otherwise you need to update <tt>PATH</tt>.
			Putting something like this in <tt>gb-config.php</tt> would do it:<br/><br/>
			
			<code>$_ENV[\'PATH\'] .= \':/opt/local/bin\';</code><br/><br/>
			
			<tt>/opt/local/bin</tt> being the directory in which git is installed.
			Alternatively edit PATH in your php.ini file.<br/><br/>
			
			<small>(Original error from shell: '.h($e->getMessage()).')</small>';
	}
	
	# -------------------------------------------------------------------------
	# create repository	
	if (!$errors) {
		$add_sample_content = isset($_POST['add-sample-content']) && $_POST['add-sample-content'] === 'true';
		if (!gb::init($add_sample_content))
			$errors[] = 'Failed to create and initialize repository at '.var_export(gb::$site_dir,1);
	}
	
	# -------------------------------------------------------------------------
	# commit changes (done by gb::init())
	if (!$errors) {
		try {
			if (!gb::commit('gitblog created', trim($_POST['name']).' <'.trim($_POST['email']).'>'))
				$errors[] = 'failed to commit creation';
		}
		catch (Exception $e) {
			$errors[] = 'failed to commit creation: '.nl2br(h(strval($e)));
		}
	}
	
	# -------------------------------------------------------------------------
	# create admin account
	if (!$errors) {
		$u = new GBUser(trim($_POST['name']), trim($_POST['email']), GBUser::passhash($_POST['passphrase']), true);
		$u->save(); # issues git add, that's why we do this after init
	}
	
	# -------------------------------------------------------------------------
	# send the client along
	if (!$errors) {
		header('Location: '.gb::$site_url);
		exit(0);
	}
}

# ------------------------------------------------------------------------------------------------
# Perform a few sanity checks
#else {
#	
#}

# ------------------------------------------------------------------------------------------------
# prepare for rendering

gb::$title[] = 'Setup';
$is_writable = is_writable(gb::$site_dir);

if (!$is_writable) {
	$errors[] = '<b>Ooops.</b> The directory <code>'.h(gb::$site_dir).'</code> is not writable.
		Gitblog need to create a few files in this directory.
		<br/><br/>
		Please make this directory (highlighted above) writable and then reload this page.';
	# todo: check if the web server user and/or is the same as user and/or group
	#       on directory. If so, suggest a chmod, otherwise suggest a chown.
}

include '_header.php';
?>
<h2>Setup your gitblog</h2>
<p>
	It's time to setup your new gitblog.
</p>
<form action="setup.php" method="post">
	
	<div class="inputgroup">
		<h4>Create an administrator account</h4>
		<p>Email:</p>
		<input type="text" name="email" value="<?= h(@$_POST['email']) ?>" />
		<p>Real name:</p>
		<input type="text" name="name" value="<?= h(@$_POST['name']) ?>" />
		<p class="note">
			This will be used for commit messages, along with email.
			Commit history can not be changed afterwards, so please provide your real name here.
		</p>
		<p>Pass phrase:</p>
		<input type="password" name="passphrase" />
		<input type="password" name="passphrase2" />
		<p class="note">
			Choose a pass phrase used to authenticate as administrator. Type it twice.
		</p>
	</div>
	
	<div class="inputgroup">
		<h4>Site settings</h4>
		<p>Title:</p>
		<input type="text" name="title" value="<?= h(gb::$site_title) ?>" />
		<p class="note">
			The title of your site can be changed later.
		</p>
		<p>
			<label>
				<input type="checkbox" value="true" checked="checked" name="add-sample-content" />
				Add sample content
			</label>
		</p>
		<p class="note">
			Add some sample content to get you started.
		</p>
	</div>
	
	<div class="breaker"></div>
	<p>
	<? if (!$is_writable): ?>
		<input type="button" value="Setup" disabled="true"/>
	<? else: ?>
		<input type="submit" name="submit" value="Setup"/>
	<? endif; ?>
	</p>
</form>
<div class="breaker"></div>
<? include '_footer.php'; ?>