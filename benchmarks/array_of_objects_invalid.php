<?php

declare(strict_arrays=1);

class Person
{
    public function __construct(public int $id, public string $name, public string $last_name) {}
}

function getUsers(): array<Person>
{
    return [
        new Person(1, 'first', 'name'),
        new Person(2, 'other', 'name'),
        42  // Invalid - not a Person object
    ];
}


$result = getUsers();

print_r($result);
