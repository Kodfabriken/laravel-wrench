<?php

namespace Kodfabriken\LaravelWrench;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Class KFModel
 * @method static Builder where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder whereDate($column, $operator, $value = null, $boolean = 'and')
 */
abstract class KFModel extends Model
{

    /** @var array $validationRules */
    public static $validationRules;

    /**
     * @param array $data
     * @param string $group
     * @return array|null
     * @internal param array $fieldsToValidate
     * @internal param bool $dataFieldsOnly
     */
    public static function validate(array $data, string $group): ?array
    {
        $validator = Validator::make($data, static::getValidationRules($group));

        if ($validator->fails()) {
            return $validator->errors()->all();
        } else {
            return null;
        }
    }

    public static function getValidationRules(string $group): array
    {
        $rules = [];

        foreach (static::$validationRules as $modelField => $groupDescriptors) {
            foreach ($groupDescriptors as $groupDescriptor => $groupRules) {
                if (!is_array($groupDescriptor)) {
                    $groupDescriptor = explode(',', $groupDescriptor);
                }

                if (in_array($group, $groupDescriptor)) {
                    if (!array_key_exists($modelField, $rules)) {
                        $rules[$modelField] = [];
                    }

                    foreach ($groupRules as $rule) {
                        $rules[$modelField][] = $rule;
                    }
                }
            }
        }

        return $rules;
    }

    public function inheritsPermissions(): ?KFModel
    {
        return null;
    }

    public function userCanView(KFUser $user = null): bool
    {
        if ($parentModel = $this->inheritsPermissions()) {
            return $parentModel->userCanView($user);
        }

        return false;
    }

    public function userCanUpdate(KFUser $user = null): bool
    {
        if ($parentModel = $this->inheritsPermissions()) {
            return $parentModel->userCanUpdate($user);
        }

        return false;
    }

    public function userCanDelete(KFUser $user = null): bool
    {
        if ($parentModel = $this->inheritsPermissions()) {
            return $parentModel->userCanDelete($user);
        }

        return false;
    }

    public function userCanCreate(KFUser $user = null): bool
    {
        if ($parentModel = $this->inheritsPermissions()) {
            return $parentModel->userCanCreate($user);
        }

        return false;
    }
}
