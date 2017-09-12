<?php namespace Bkwld\Upchuck;

// Deps
use Bkwld\Upchuck\Storage;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Respond to model saving and deleting events
 */
class Observer {

    /**
     * @var Illuminate\Http\Request
     */
    private $request;

    /**
     * @var Bkwld\Upchuck\Storage
     */
    private $storage;

    /**
     * Dependency injection
     *
     * @param Bkwld\Upchuck\Storage $storage
     */
    public function __construct(Storage $storage) {
        $this->storage = $storage;
    }

    /**
     * A model is saving, check for files being uploaded
     *
     * @param  string $event
     * @param  array $payload containg:
     *   - Illuminate\Database\Eloquent\Model
     * @return void
     */
    public function onSaving($event, $payload) {

        // Destructure params
        list($model) = $payload;

        // Check that the model supports uploads through Upchuck
        if (!$this->supportsUploads($model)
            || !($attributes = $model->getUploadAttributes())) return;

        // Loop through the all of the upload attributes ...
        event(new Events\HandlingSaving($model));
        foreach($attributes as $attribute) {

            // Check if there is an uploaded file in the upload attribute
            if (($file = $model->getAttribute($attribute))
                && is_a($file, UploadedFile::class)) {

                // Move the upload and get the new URL
                $url = $this->storage->moveUpload($file);
                $model->setUploadAttribute($attribute, $url);
            }

            // If the attribute field is dirty, delete the old image
            if ($model->isDirty($attribute) && ($old = $model->getOriginal($attribute))) {
                $this->storage->delete($old);
            }
        }
        event(new Events\HandledSaving($model));
    }

    /**
     * A model has been deleted, trash all of it's files
     *
     * @param  string $event
     * @param  array $payload containg:
     *   - Illuminate\Database\Eloquent\Model
     * @return void
     */
    public function onDeleted($event, $payload) {

        // Destructure params
        list($model) = $payload;

        // Check that the model supports uploads through Upchuck
        if (!$this->supportsUploads($model)
            || !($attributes = $model->getUploadAttributes())) return;

        // Loop through the all of the upload attributes and get the values using
        // "original" so that you get the file value before it may have been cleared.
        event(new Events\HandlingDeleted($model));
        foreach($attributes as $attribute) {
            if (!$url = $model->getOriginal($attribute)) continue;
            $this->storage->delete($url);
        }
        event(new Events\HandledDeleted($model));
    }

    /**
     * Check that the model supports uploads through Upchuck.  Not detecting the
     * trait because it doesn't report to subclasses.
     *
     * @param Illuminate\Database\Eloquent\Model $model
     * @return boolean
     */
    public function supportsUploads($model) {
        return method_exists($model, 'getUploadAttributes');
    }

}
