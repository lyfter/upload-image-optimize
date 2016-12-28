<?php
/*
Plugin Name: Image upload resize
Plugin URI:
Description: ImageMagick resize and optimize images when uploaded.
Author: Joep Suijkerbuijk
Author URI: http://www.joepsuijkerbuijk.com/
Version: 1.0.0
License: GPL v3
*/



function  imageUploadResizeRegisterSettings()
{

    add_option('MaxSize', '2500');
    register_setting('imageUploadResizeSettings', 'MaxSize');

    add_option('CompressionQuality', '75');
    register_setting('imageUploadResizeSettings', 'CompressionQuality');

}


function imageUploadResizeRegisterOptionsPage()
{
    add_media_page('ImageMagick Image Upload Resize', 'Image compression', 'manage_options', __FILE__, 'imageUploadResizeOptionsPage');
}


function imageUploadResizePluginDeactivate()
{
    delete_option('MaxSize');
    delete_option('CompressionQuality');
}


function imageUploadResizeOptionsPage()
{
    ?>

    <div class="wrap">

        <h2>Image upload resize</h2>

        <p>
            <?php
            if (extension_loaded('imagick') || class_exists('Imagick')) {
                // imagick PHP module is installed
                echo 'ImageMagick PHP Module: <span style="color: green; font-weight: bolder">OK, installed';
            } else {
                echo 'ImageMagick PHP Module: <span style="color: red; font-weight: bolder">MISSING, not installed';
            }
            ?>
        </p>

        <form method="post" action="options.php">

            <?php settings_fields('imageUploadResizeSettings'); ?>
            <?php do_settings_sections('imageUploadResizeSettings'); ?>

            <table class="form-table">

                <tr valign="top">
                    <th scope="row">Maximum size:</th>
                    <td><input type="text" name="MaxSize" value="<?php echo get_option('MaxSize'); ?>"/></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Compression Quality:</th>
                    <td><input type="text" name="CompressionQuality"
                               value="<?php echo get_option('CompressionQuality'); ?>"/></td>
                </tr>

            </table>

            <?php submit_button(); ?>

        </form>

    </div>


    <?php

}


function modifyMainImageOnUpload($file)
{

    $allowedFileTypes = array(
        'image/jpeg',
        'image/gif',
        'image/png',
        'image/bmp',
        'image/x-icon',
        'image/tiff'
    );

    $compressionQuality = get_option('CompressionQuality');
    $maxSize = get_option('MaxSize');

    if (!in_array($file['type'], $allowedFileTypes)) {
        return $file;
    }

    $image = new Imagick($file['file']);

    $size = @getimagesize($file['file']);

    if (!$size) {
        return new WP_Error('invalid_image', __('Could not read image size.'));
    }

    // If wanted max size is smaller than the current image width or height
    if ($image->getImageWidth() > $maxSize || $image->getImageHeight() > $maxSize) {

        // Resizes to whichever is larger, width or height
        if ($image->getImageHeight() <= $image->getImageWidth()) {
            // Resize image using the lanczos resampling algorithm based on width
            $image->resizeImage($maxSize, 0, Imagick::FILTER_LANCZOS, 1);
        } else {
            // Resize image using the lanczos resampling algorithm based on height
            $image->resizeImage(0, $maxSize, Imagick::FILTER_LANCZOS, 1);
        }
    }

    $image->setImageFormat('jpg');

    // set image compression quality if it is higher than our wanted quality
    if ($image->getImageCompressionQuality() > $compressionQuality) {
        $image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality($compressionQuality);
    }

    // Save 'progresive' // TODO check if this does work?
    $image->setimageinterlacescheme(Imagick::INTERLACE_PLANE);

    // Strip out unneeded meta data
    $image->stripImage();


    $image->writeImage($file['file']);

    // Remove the JPG from memory
    $image->destroy();

    return $file;
}

add_filter('wp_handle_upload', 'modifyMainImageOnUpload', 10, 2);

register_deactivation_hook(__FILE__, 'imageUploadResizePluginDeactivate');
add_action('admin_init', 'imageUploadResizeRegisterSettings');
add_action('admin_menu', 'imageUploadResizeRegisterOptionsPage');
