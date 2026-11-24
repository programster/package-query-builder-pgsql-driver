<?php

/*
 * Tests AllowedFields: which fields each way of creating it allows, and that it can't be created allowing nothing.
 */

use Programster\QueryBuilderPgsqlDriver\AllowedFields;
use Programster\QueryBuilderPgsqlDriver\Testing\SmokeTest;

/** @var SmokeTest $smoke */

$smoke->run("allowed fields: only the given fields are allowed", function (): void {
    $fields = AllowedFields::createUsingFields('name', 'age');
    SmokeTest::assertSame(true, $fields->isAllowed('name'), "name");
    SmokeTest::assertSame(true, $fields->isAllowed('age'), "age");
    SmokeTest::assertSame(false, $fields->isAllowed('password_hash'), "password_hash");
});

$smoke->run("allowed fields: a field is only allowed in the same case", function (): void {
    $fields = AllowedFields::createUsingFields('name');
    SmokeTest::assertSame(false, $fields->isAllowed('Name'), "Name");
    SmokeTest::assertSame(false, $fields->isAllowed('NAME'), "NAME");
});

$smoke->run("allowed fields: a field isn't allowed with extra spaces", function (): void {
    SmokeTest::assertSame(false, AllowedFields::createUsingFields('name')->isAllowed('name '), "name with a space");
});

$smoke->run("allowed fields: createAny() allows any field", function (): void {
    $fields = AllowedFields::createAny();
    SmokeTest::assertSame(true, $fields->isAllowed('password_hash'), "password_hash");
    SmokeTest::assertSame(true, $fields->isAllowed('my "field"'), "a field with quotes");
});

$smoke->run("allowed fields: an empty field is rejected", function (): void {
    $createWithEmptyFirst = function (): void {
        AllowedFields::createUsingFields('');
    };

    $createWithEmptyLater = function (): void {
        AllowedFields::createUsingFields('name', '');
    };

    SmokeTest::expectException(\InvalidArgumentException::class, $createWithEmptyFirst, 'empty string');
    SmokeTest::expectException(\InvalidArgumentException::class, $createWithEmptyLater, 'empty string');
});

# A list built elsewhere (e.g. from config) that turns out to be empty must fail, rather than allowing every field.
$smoke->run("allowed fields: an empty list is rejected where it's given", function (): void {
    $noFields = [];

    $createWithNoFields = function () use ($noFields): void {
        AllowedFields::createUsingFields(...$noFields);
    };

    SmokeTest::expectException(\InvalidArgumentException::class, $createWithNoFields, 'At least one allowed field');
});
