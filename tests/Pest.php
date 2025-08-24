<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use JTD\FirebaseModels\Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(TestCase::class)->in('Unit/Restructured');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the amount of code you type.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Custom Expectations for Firebase Models
|--------------------------------------------------------------------------
|
| These custom expectations help with testing Firebase Models functionality
| and provide more readable test assertions.
|
*/

expect()->extend('toBeFirestoreModel', function () {
    return $this->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModel::class);
});

expect()->extend('toHaveAttribute', function (string $attribute, mixed $value = null) {
    $model = $this->value;

    if (!$model instanceof \JTD\FirebaseModels\Firestore\FirestoreModel) {
        throw new InvalidArgumentException('Expected a FirestoreModel instance');
    }

    if ($value === null) {
        return expect($model->hasAttribute($attribute))->toBeTrue();
    }

    return expect($model->getAttribute($attribute))->toBe($value);
});

expect()->extend('toHaveCast', function (string $attribute, string $castType) {
    $model = $this->value;

    if (!$model instanceof \JTD\FirebaseModels\Firestore\FirestoreModel) {
        throw new InvalidArgumentException('Expected a FirestoreModel instance');
    }

    return expect($model->hasCast($attribute, $castType))->toBeTrue();
});

expect()->extend('toBeDirty', function (array|string|null $attributes = null) {
    $model = $this->value;

    if (!$model instanceof \JTD\FirebaseModels\Firestore\FirestoreModel) {
        throw new InvalidArgumentException('Expected a FirestoreModel instance');
    }

    return expect($model->isDirty($attributes))->toBeTrue();
});

expect()->extend('toBeClean', function (array|string|null $attributes = null) {
    $model = $this->value;

    if (!$model instanceof \JTD\FirebaseModels\Firestore\FirestoreModel) {
        throw new InvalidArgumentException('Expected a FirestoreModel instance');
    }

    return expect($model->isClean($attributes))->toBeTrue();
});

expect()->extend('toExistInFirestore', function () {
    $model = $this->value;

    if (!$model instanceof \JTD\FirebaseModels\Firestore\FirestoreModel) {
        throw new InvalidArgumentException('Expected a FirestoreModel instance');
    }

    return expect($model->exists)->toBeTrue();
});

expect()->extend('toBeRecentlyCreated', function () {
    $model = $this->value;

    if (!$model instanceof \JTD\FirebaseModels\Firestore\FirestoreModel) {
        throw new InvalidArgumentException('Expected a FirestoreModel instance');
    }

    return expect($model->wasRecentlyCreated)->toBeTrue();
});
