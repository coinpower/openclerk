<?php
/**
 * Requires login.
 */

$summaries = array();
$q = db()->prepare("SELECT * FROM summaries WHERE user_id=?");
$q->execute(array(user_id()));
while ($s = $q->fetch()) {
	$summaries[$s['summary_type']] = $s;
}
?>

<p>
I would like currency summaries provided in... (<a href="<?php echo htmlspecialchars(url_for('premium')); ?>">up to <?php echo number_format(get_premium_value($user, 'summaries')); ?></a>)
</p>

<ul>
<?php foreach (get_summary_types() as $key => $summary) { ?>
	<li>
		<label><input type="checkbox" name="<?php echo htmlspecialchars($key); ?>" value="1"<?php if (isset($summaries[$key])) echo " checked"; ?>>
			<?php echo $summary['title']; ?> <?php if (in_array($summary['currency'], get_new_supported_currencies())) echo " <span class=\"new\">new</span>"; ?></label>
	</li>
<?php } ?>
</ul>

<p class="warning-inline">
<b>NOTE:</b> Removing a currency will also permanently remove any historical summary data for that currency.
</p>