<?php
function GPG_handleconfig($configVal) {
	return true;
}

function GPG_addHeader($target) {
	global $pluginURL, $blog, $pageTitle,$entries, $configVal;

	if (is_null($configVal) || empty($configVal)) {
		$config = array('publickey'=>'');
	} else {
		$config = Setting::fetchConfigVal($configVal);
	}

	$pubkey = trim($config['publickey']);
	if (strlen($pubkey) == 0) {
		$target .= '<!-- No public key -->';
		return $target;
	}

	$target .= '
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/openpgp.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/rng/default/entropy.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/rng/default/simplerng.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/rng/default.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/sha1/default/sha1.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/sha1/default.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/aes/default/aes-enc.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/aes/default.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/bigint/default/bigint.js"></script>
		<script type="text/javascript" src="'.$pluginURL.'/javascripts/openpgp-js/bigint/default.js"></script>
	';
	ob_start();
?>
<script type="text/javascript">
	jQuery(document).ready(function(){
		var gpg_publickey_armored = '';
<?php foreach (explode("\n", $config['publickey']) as $line) { ?>
		gpg_publickey_armored += '<?=$line?>\n';
<?php } ?>
		var gpg_publickey;

		function attach_cipher_to_comments() {
			jQuery('textarea[name="comment"]').each(function(index, textarea){
				textarea = jQuery(textarea);
				var stash = textarea.parent().find('textarea.stash');
				if (stash.length > 0) {
					return;
				}

				function find_secret_checkbox() {
					var c = textarea.parent();
					while (c != document) {
						var secret = c.find('input[name="secret"]');
						if (secret.length > 0) {
							return secret;
						}
						c = c.parent();
					}
					return null;
				}
				var secret_checkbox = find_secret_checkbox();
				if (secret_checkbox == null) {
					throw 'cant find secret_checkbox';
				}

				function find_submit_button() {
					var c = textarea.parent();
					while (c != document) {
						var submit = c.find('input[type="submit"]');
						if (submit.length > 0) {
							return submit;
						}
						c = c.parent();
					}
					return null;
				}
				var submit_button = find_submit_button();
				if (submit_button == null) {
					throw 'cant find submit button';
				}

				var stash = jQuery(document.createElement('textarea'));
				stash.addClass('stash');
				stash.hide();
				textarea.after(stash);
				var encbtn = jQuery(document.createElement('input'));
				encbtn.attr('type', 'button');
				encbtn.attr('value', 'GPG 암호화');
				secret_checkbox.before(encbtn);
				var rstbtn = jQuery(document.createElement('input'));
				rstbtn.attr('type', 'button');
				rstbtn.attr('value', '재편집');
				encbtn.after(rstbtn);

				function encode_utf8( s ) {
					return unescape( encodeURIComponent( s ) );
				}
				function encrypt() {
					var cleartext = textarea.val();
					stash.val(cleartext);
					var ciphertext = OpenPGP.encrypt(gpg_publickey, OpenPGP.ALGO_SYMM_AES256, encode_utf8(cleartext));
					textarea.val(ciphertext);
					textarea.attr('readonly', 'readonly');
					encbtn.hide();
					rstbtn.show();
				}
				function restore() {
					var cleartext = stash.val();
					textarea.val(cleartext);
					textarea.attr('readonly', '');
					encbtn.show();
					rstbtn.hide();
				}
				encbtn.click(encrypt);
				rstbtn.click(restore);
				rstbtn.hide();
			});
		}

		entropy.startCollect();
		gpg_publickey = OpenPGP.PublicKeyMessage.load_armored(gpg_publickey_armored);
		jQuery('#footer').prepend(jQuery('<pre id="pubkey">'+gpg_publickey_armored+'</pre>'));
		attach_cipher_to_comments();
		var original_loadComment = window.loadComment;
		window.loadComment = function(entryId, page, force) {
			var request = new HTTPRequest("POST", blogURL + '/comment/load/' + entryId);
			var o = document.getElementById("entry" + entryId + "Comment");
			if ((!force && o.style.display == 'none') || force) {
				request.onSuccess = function () {
					PM.removeRequest(this);
					o.innerHTML = this.getText("/response/commentBlock");
			//			window.location.href = '#entry' + entryId + 'Comment';
					attach_cipher_to_comments();
				};
				request.onError = function() {
					PM.removeRequest(this);
					PM.showErrorMessage("Loading Failed.","center","bottom");
				};
				PM.addRequest(request,"Loading Comments...");
				request.send('&page='+page);
			}
			if (!force)
				o.style.display = (o.style.display == 'none') ? 'block' : 'none';
		}
	});
</script>
<?php
	$target .= ob_get_clean();
	return $target;
}
