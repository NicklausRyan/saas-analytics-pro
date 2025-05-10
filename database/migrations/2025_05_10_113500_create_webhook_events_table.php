<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebhookEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->integer('website_id')->unsigned()->nullable()->index();
            $table->string('processor', 32)->index(); // stripe, paypal, etc.
            $table->string('event_type', 128)->index(); // event type from the payload
            $table->string('event_id', 128)->nullable(); // ID of the event from the provider
            $table->text('payload')->nullable(); // Full webhook payload
            $table->text('headers')->nullable(); // Request headers
            $table->boolean('is_valid_signature')->default(false); // Whether signature was valid
            $table->string('ip_address', 45)->nullable(); // IP address that sent the webhook
            $table->string('status', 16)->default('processed'); // processed, failed, etc.
            $table->text('error_message')->nullable(); // Any error message if failed
            $table->integer('processing_time')->nullable(); // Time taken to process in milliseconds
            $table->timestamps();
            
            // Add foreign key to websites table
            $table->foreign('website_id')
                ->references('id')
                ->on('websites')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('webhook_events');
    }
}
