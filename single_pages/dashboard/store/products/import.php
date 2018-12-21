<?php  defined('C5_EXECUTE') or die('Access denied'); ?>

<?php
$importFID = File::getByID(intval(Config::get('community_store_import.import_file')));
$imageFID = File::getByID(intval(Config::get('community_store_import.default_image')));
?>

<style>
div.col-md-6 {
    margin-bottom: 20px;
}
</style>

<form method="post" class="form-horizontal" id="import-form" action="<?php echo $view->action('run') ?>" >
    <?php echo $this->controller->token->output('run_import')?>

    <fieldset>
        <legend><?php echo t('Settings') ?></legend>

        <p style="margin-bottom: 25px; color: #aaa; display: block;"
           class="small"><?php echo t('Note: These settings will be saved prior to import.') ?></p>

        <div class="form-group">
            <div class="col-md-6">
                <label class="control-label"><?php echo t('Product Import File') ?></label>
                <?php echo $concrete_asset_library->file('ccm-import-file', 'import_file', 'Choose File', $importFID) ?>
                <div class="help-block"><?php echo t('Choose the CSV file to import.') ?></div>
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-6">
                <label class="control-label"><?php echo t('Default Product Image') ?></label>
                <?php echo $concrete_asset_library->file('ccm-default-file', 'default_image', t('Choose File'), $imageFID); ?>
                <div class="help-block"><?php echo t('Choose the image to use for each imported product.') ?></div>
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-2">
                <label class="control-label"><?php echo t('Field Delimiter') ?></label>
                <?php echo $form->text('delimiter', Config::get('community_store_import.csv.delimiter')) ?>
                <div class="help-block"><?php echo t('Enter tab as \t.') ?></div>
            </div>
            <div class="col-md-2">
                <label class="control-label"><?php echo t('Field Enclosure') ?></label>
                <?php echo $form->text('enclosure', Config::get('community_store_import.csv.enclosure')) ?>
                <div class="help-block"><?php echo t('') ?></div>
            </div>
            <div class="col-md-2">
                <label class="control-label"><?php echo t('Line Length') ?></label>
                <?php echo $form->text('line_length', Config::get('community_store_import.csv.line_length')) ?>
                <div class="help-block"><?php echo t('') ?></div>
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-6">
                <label class="control-label"><?php echo t('Max Time') ?></label>
                <?php echo $form->text('max_execution_time', Config::get('community_store_import.max_execution_time')) ?>
                <div class="help-block"><?php echo t('Product import can take some time. Enter the number of seconds to allow the import to run. 1 second per product should be sufficient.') ?></div>
            </div>
        </div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <button id="import" class='btn btn-primary pull-right'><?php echo t('Save & Import'); ?></button>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    $('#import').click(function() {
        return confirm('<?php echo t("Be sure you backup your database before continuing. Are you sure you want to continue?"); ?>');
    });
});
</script>
