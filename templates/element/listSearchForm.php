<?php
    use Cake\Core\Configure;
?>

<p style="margin:15px 0 5px 0;"><?php echo $label; ?></p>

<?php
echo $this->Form->create(null, [
    'id' => 'list-search-form',
    'type' => 'get',
]);

echo $this->Form->control('keyword', ['label' => '', 'value' => $keyword, 'disabled' => true]);
if (!empty($categories)) {
    echo $this->Form->hidden('categories', ['label' => '', 'value' => $categories]);
}
if ($useTimeRange) {
    echo $this->Form->control('timeRange', ['type' => 'select', 'label' => '', 'options' => $timeRangeOptions, 'value' => $timeRange, 'disabled' => true]);
}
if (Configure::read('AppConfig.onlineEventsEnabled') && $showIsOnlineEventCheckbox) {
    echo $this->Form->control('isOnlineEvent', ['hiddenField' => false, 'type' => 'checkbox', 'label' => 'Online-Event?', 'checked' => $isOnlineEvent, 'disabled' => true]);
}

if ($resetButton) { ?>
    <a href="<?php echo $baseUrl; ?>" class="button gray"><?php echo __('Clear'); ?></a>
<?php }
