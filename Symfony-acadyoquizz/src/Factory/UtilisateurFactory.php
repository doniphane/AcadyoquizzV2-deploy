<?php

namespace App\Factory;

use App\Entity\Utilisateur;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Utilisateur>
 */
final class UtilisateurFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        $firstName = self::faker()->firstName();
        $lastName = self::faker()->lastName();

        return [
            'email' => strtolower($firstName.'.'.$lastName).self::faker()->numberBetween(1, 9999).'@example.com',
            'roles' => ['ROLE_USER'],
            // bcrypt for "password"
            'password' => '$2y$13$C1sU4m0C2nqg2ijm2Z4tE.Z2cR7I9pQf7m1t1o8bQmp0QF0b0b0b0',
            'firstName' => $firstName,
            'lastName' => $lastName,
            'resetToken' => null,
            'resetTokenExpiresAt' => null,
        ];
    }

    public static function class(): string
    {
        return Utilisateur::class;
    }
}


