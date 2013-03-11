<?php
/**
 * Version Sync View File.
 *
 * Used to render the HTML for warning is db or software version are mismatches
 * 
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Robbie Mackay <rm@robbiemackay.com>
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     API Controller
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */
?>

<?php 
if ( Kohana::config('config.enable_ver_sync_warning') == TRUE)
{	
	if ( isset($js)) 
	{ ?>
		<script type="text/javascript" charset="utf-8">
			<?php echo $js; ?>
		</script>
		<span id="to_upgrade" style="display:none;"></span>
		<?php if (( url::current() != "admin/upgrader") AND (Kohana::config('version.ushahidi_db_version') != Kohana::config('settings.db_version')))
		{ ?>
		<li><span id="version-sync-db" class="update-info">
			<?php echo Kohana::lang('upgrade.upgrade_warning_db_version'); ?><br />
			version.php: <?php echo Kohana::config('version.ushahidi_db_version') ?> &nbsp; <?php echo Kohana::lang('upgrade.upgrade_database'); ?>  <?php echo Kohana::config('settings.db_version') ?>
		</span></li>
	<?php
		}
	}
} ?>