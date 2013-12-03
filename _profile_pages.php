
<ul class="page_list">
<?php $first = true; foreach ($pages as $page) {
	$args = array('page' => $page['id']);
	if (require_get("demo", false)) {
		$args['demo'] = require_get("demo");
	} ?>
	<li class="page_tab<?php echo htmlspecialchars($page['id']); ?><?php if (!require_get("securities", false) && (!$page_id || $page['id'] == $page_id)) echo " page_current"; ?>"><a href="<?php echo htmlspecialchars(url_for('profile', $args)); ?>">
		<?php echo htmlspecialchars($page['title']); ?>
	</a></li>
<?php $first = false; } ?>
	<?php
	$args = array();
	if (require_get("demo", false)) {
		$args['demo'] = require_get("demo");
	} ?>
	<li class="page_tabcurrencies<?php if (isset($your_currencies) && $your_currencies) echo " page_current"; ?>"><a href="<?php echo htmlspecialchars(url_for('your_currencies', $args)); ?>">
		Your Currencies <span class="new">new</span>
	</a></li>
	<?php
	$args = array('securities' => 1);
	if (require_get("demo", false)) {
		$args['demo'] = require_get("demo");
	} ?>
	<li class="page_tabsecurities<?php if (require_get("securities", false)) echo " page_current"; ?> premium"><a href="<?php echo htmlspecialchars(url_for('profile', $args)); ?>">
		Your Securities (<?php echo number_format($securities_count); ?>)
	</a></li>
</ul>
