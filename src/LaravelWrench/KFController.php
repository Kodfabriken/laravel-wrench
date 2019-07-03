<?php

namespace Kodfabriken\LaravelWrench;

use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;

/**
 * Class KFController
 * @package Kodfabriken\LaravelWrench
 * @property KFUser $user
 */
class KFController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __get($name)
    {
        if ($name === 'user') {
            return auth()->guard('api')->user();
        }

        return $this->$name;
    }

    /**
     * @param array $errors
     * @param $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($errors = [], $statusCode)
    {
        return response()->json([
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * @param $data
     * @param $key
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = null, $key = null, $statusCode = 200)
    {
        if ($data === null && $key === null) {
            return response()->json();
        }

        return response()->json([
            $key => $data
        ], $statusCode);
    }

    /**
     * @param Request $request
     * @param $rules
     */
    protected function validate(Request $request, $rules)
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new HttpResponseException($this->errorResponse($validator->errors()->all(), 400));
        }
    }

    /**
     * @param $modelClass
     * @param array $data
     * @param string $validationGroup
     */
    protected function validateModel($modelClass, array $data, string $validationGroup)
    {
        $errors = $modelClass::validate($data, $validationGroup);

        if ($errors) {
            throw new HttpResponseException($this->errorResponse($errors, 400));
        }
    }

    /**
     * @param $modelClass
     * @param int $modelId
     * @return KFModel
     */
    protected function fetchModel($modelClass, int $modelId, KFUser $contextUser = null): KFModel
    {
        /** @var KFModel $model */
        $model = $modelClass::find($modelId);

        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        if (!$model) {
            $function = new ReflectionClass($modelClass);
            throw new HttpResponseException($this->errorResponse(["{$function->getShortName()} not found"], 404));
        }

        if (!$model->userCanView($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        return $model;
    }

    protected function fetchRelatedModel($baseModelClass, int $baseModelId, $relation, $relatedId, KFUser $contextUser = null): KFModel
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        $baseModel = $this->fetchModel($baseModelClass, $baseModelId, $contextUser);

        /** @var KFModel $relatedModel */
        $relatedModel = $baseModel->$relation()->find($relatedId);

        if (!$relatedModel) {
            throw new HttpResponseException($this->errorResponse("{$relation} not found", 404));
        }

        if (!$relatedModel->userCanView($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        return $relatedModel;
    }

    protected function fetchNestedModel($baseModelClass, int $baseModelId, array $nestedRelationPath, KFUser $contextUser = null): KFModel
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        $baseModel = $this->fetchModel($baseModelClass, $baseModelId, $contextUser);

        /** @var KFModel $relatedModel */
        $relatedModel = $baseModel;

        foreach ($nestedRelationPath as $relationName => $relatedId) {
            /** @var KFModel $relatedModel */
            $relatedModel = $relatedModel->$relationName()->find($relatedId);

            if (!$relatedModel) {
                throw new HttpResponseException($this->errorResponse("{$relationName} not found", 404));
            }
        }

        if (!$relatedModel->userCanView($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        return $relatedModel;
    }

    /**
     * @param $modelClass
     * @param array $fillableValues
     * @param array $nonFillableValues
     * @param string $validationGroup
     * @return KFModel
     */
    protected function createModel($modelClass, array $fillableValues, array $nonFillableValues = [], $validationGroup = 'create', KFUser $contextUser = null): KFModel
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        $this->validateModel($modelClass, array_merge($fillableValues, $nonFillableValues), $validationGroup);

        /** @var KFModel $model */
        $model = new $modelClass();

        $model->fill($fillableValues);

        foreach ($nonFillableValues as $key => $value) {
            $model->$key = $value;
        }

        if (!$model->userCanCreate($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        $model->save();

        return $model;
    }

    /**
     * @param $modelClass
     * @param int $modelId
     * @param array $fillableValues
     * @param array $nonFillableValues
     * @param string $validationGroup
     * @return KFModel
     */
    protected function patchModel($modelClass, int $modelId, array $fillableValues, array $nonFillableValues = [], $validationGroup = 'update', KFUser $contextUser = null): KFModel
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        /** @var KFModel $model */
        $model = $this->fetchModel($modelClass, $modelId, $contextUser);

        $model->validate(array_merge($fillableValues, $nonFillableValues), $validationGroup);
        $model->fill($fillableValues);

        foreach ($nonFillableValues as $key => $value) {
            $model->$key = $value;
        }

        if (!$model->userCanUpdate($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        $model->save();

        return $model;
    }

    /**
     * @param $baseModelClass
     * @param int $baseModelId
     * @param $relationName
     * @param array $fillableValues
     * @param string $validationGroup
     * @return KFModel
     */
    protected function createRelatedModel($baseModelClass, int $baseModelId, $relationName, array $fillableValues, $validationGroup = 'create', KFUser $contextUser = null): KFModel
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        /**
         * @var KFModel $baseModel
         * Fetches our basemodel, which we wish to attach the related model to
         */
        $baseModel = $this->fetchModel($baseModelClass, $baseModelId, $contextUser);

        /** @var HasOneOrMany $relation */
        $relation = $baseModel->$relationName();

        $reflection = new ReflectionClass($relation->getRelated());
        $relatedModel = $reflection->getName();

        $this->validateModel($relatedModel, $fillableValues, $validationGroup);

        /** @var KFModel $newModel */
        $newModel = new $relatedModel();
        $newModel->fill($fillableValues);
        $newModel->setAttribute($relation->getForeignKeyName(), $baseModelId);

        if (!$newModel->userCanCreate($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        $newModel->save();

        return $newModel;
    }

    /**
     * @param $modelClass
     * @param int $modelId
     * @param array $fillableValues
     * @param array $nonFillableValues
     * @param string $validationGroup
     * @return KFModel
     */
    protected function deleteModel($modelClass, int $modelId, KFUser $contextUser = null): KFModel
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        /** @var KFModel $model */
        $model = $this->fetchModel($modelClass, $modelId, $contextUser);

        if (!$model->userCanDelete($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        $model->delete();

        return $model;
    }

    /**
     * @param $baseModelClass
     * @param int $baseModelId
     * @param $relationName
     * @param $relatedId
     * @param KFUser|null $contextUser
     */
    protected function deleteRelatedModel($baseModelClass, int $baseModelId, $relationName, $relatedId, KFUser $contextUser = null)
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        /** @var KFModel $relatedModel */
        $relatedModel = $this->fetchRelatedModel($baseModelClass, $baseModelId, $relationName, $relatedId, $contextUser);

        if (!$relatedModel->userCanDelete($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        $relatedModel->delete();
    }

    /**
     * @param $baseModelClass
     * @param int $baseModelId
     * @param $relationName
     * @param $relatedId
     * @param array $fillableValues
     * @param array $nonFillableValues
     * @param string $validationGroup
     * @param KFUser|null $contextUser
     */
    protected function patchRelatedModel($baseModelClass, int $baseModelId, $relationName, $relatedId, array $fillableValues, array $nonFillableValues = [], $validationGroup = 'update', KFUser $contextUser = null)
    {
        if ($contextUser === null) {
            $contextUser = $this->user;
        }

        $relatedModel = $this->fetchRelatedModel($baseModelClass, $baseModelId, $relationName, $relatedId, $contextUser);

        if (!$relatedModel->userCanUpdate($contextUser)) {
            throw new HttpResponseException($this->errorResponse(["Insufficient permissions"], 403));
        }

        $model->validate(array_merge($fillableValues, $nonFillableValues), $validationGroup);
        $model->fill($fillableValues);

        foreach ($nonFillableValues as $key => $value) {
            $model->$key = $value;
        }

        $model->save();
    }
}
