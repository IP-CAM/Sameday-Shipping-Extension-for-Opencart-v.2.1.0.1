<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-custom" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
            <h1><?php echo $heading_title_service; ?></h1>
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="container-fluid">
        <?php if ($error_success) { ?>
        <div class="alert alert-success"><i class="fa fa-check"></i> <?php echo $error_success; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <?php if ($error_warning) { ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit_service; ?></h3>
            </div>
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-custom" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_name; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="name" value="<?php echo $name; ?>" placeholder="<?php echo $entry_name; ?>" id="input-name" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-price"><?php echo $entry_price; ?></label>
                        <div class="col-sm-10">
                            <input type="number" name="price" value="<?php echo $price; ?>" placeholder="<?php echo $entry_price; ?>" min="0" id="input-price" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-price"><?php echo $entry_price_free; ?></label>
                        <div class="col-sm-10">
                            <input type="number" name="price_free" value="<?php echo $price_free; ?>" placeholder="<?php echo $entry_price_free; ?>" min="0" id="input-price" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
                        <div class="col-sm-10">
                            <select name="status" id="input-status" class="form-control">
                                <?php foreach ($statuses as $key) { ?>
                                    <option value="<?php echo $key['value']; ?>" <?php if ($key['value'] == $status) { ?> selected="selected" '<?php } ?>'> <?php echo $key['text']; ?> </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php echo $footer; ?>

<script type="text/javascript">

        $('.date_from').datetimepicker({
            sideBySide: true,
            format: 'LT',
            icons: {
                time: 'fa fa-time',
                date: 'fa fa-calendar',
                up: 'fa fa-chevron-circle-up',
                down: 'fa fa-chevron-circle-down',
            },

            inline: true,
            maskInput: false,           // disables the text input mask
            pickDate: false,            // disables the date picker
            pickTime: true,            // disables de time picker
            pick12HourFormat: true,   // enables the 12-hour format time picker
            pickSeconds: false,         // disables seconds in the time picker
            startDate: -Infinity,      // set a minimum date
            endDate: Infinity        // set a maximum date
        });

        $('.date_to').datetimepicker({
            format: 'LT',
            sideBySide: true,
            icons: {
                time: 'fa fa-time',
                date: 'fa fa-calendar',
                up: 'fa fa-chevron-circle-up',
                down: 'fa fa-chevron-circle-down',
            },

            inline: true,
            maskInput: false,           // disables the text input mask
            pickDate: false,            // disables the date picker
            pickTime: true,            // disables de time picker
            pick12HourFormat: true,   // enables the 12-hour format time picker
            pickSeconds: false,         // disables seconds in the time picker
            startDate: -Infinity,      // set a minimum date
            endDate: Infinity        // set a maximum date
        });

    $('#input-status').trigger('change');
</script>





