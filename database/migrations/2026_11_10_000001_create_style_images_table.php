<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR: the image bytes live on a Flysystem disk (Google Drive by default), only metadata
// lives here. `disk` + `path` are stored per row so a style's images keep resolving even
// if STYLE_IMAGES_DISK is later switched (e.g. to S3) for new uploads.
// ADR: rows are hard-deleted (not soft-deleted) — an image is a replaceable attachment,
// not a business-critical record, and a soft-deleted row would keep a Drive file alive.
return new class extends Migration
{
    public function up()
    {
        Schema::create('style_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('style_id');
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('style_id', 'idx_style_images_style');
            $table->foreign('style_id', 'fk_style_images_style')->references('id')->on('styles')->onDelete('cascade');
            $table->foreign('created_by_id', 'fk_style_images_created_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('style_images');
    }
};
