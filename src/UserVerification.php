<?php

namespace Jrean\UserVerification;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Jrean\UserVerification\Exceptions\ModelNotCompliantException;
use Jrean\UserVerification\Exceptions\UserNotFoundException;
use Jrean\UserVerification\Exceptions\UserAlreadyValidatedException;

class UserVerification
{
    /**
     * Mailer instance.
     *
     * @var \Illuminate\Contracts\Mail\Mailer
     */
    protected $mailer;

    /**
     * Schema builder instance.
     *
     * @var \Illuminate\Database\Schema\Builder
     */
    protected $schema;

    /**
     * Constructor.
     *
     * @param  \Illuminate\Contracts\Mail\Mailer    $mailer
     * @param  \Illuminate\Database\Schema\Builder  $schema
     * @return void
     */
    public function __construct(MailerContract $mailer, Builder $schema)
    {
        $this->mailer = $mailer;

        $this->schema = $schema;
    }

    /**
     * Generate and save a verification token for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return bool
     */
    public function generate(AuthenticatableContract $user)
    {
        return $this->saveToken($user, $this->generateToken($user->email));
    }

    /**
     * Send by email a link containing the verification token.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $subject
     * @return bool
     */
    public function send(AuthenticatableContract $user, $subject = null)
    {
        if (! $this->isCompliant($user)) {
            throw new VerificationException();
        }

        return (boolean) $this->emailVerificationLink($user, $subject);
    }

    /**
     * Process the token verification for the given token.
     *
     * @param  stdClass  $user
     * @param  string  $token
     * @return bool
     */
    public function process($user, $token)
    {
        if (! $this->compareToken($user->verification_token, $token)) {
            return false;
        }

        $this->wasVerified($user);

        return true;
    }

    /**
     * Check if the user is verified.
     *
     * @param  stdClass  $user
     * @return bool
     */
    public function isVerified($user)
    {
        return $user->verified == true;
    }

    /**
     * Get user object.
     *
     * @param  string  $token
     * @param  string  $table
     * @return stdClass
     */
    public function getUser($token, $table)
    {
        return $this->getUserByToken($token, $table);
    }

    /**
     * Update and save the user as verified.
     *
     * @param  stdClass  $user
     * @return void
     */
    protected function wasVerified($user)
    {
        $user->verification_token = null;

        $user->verified = true;

        $this->updateUser($user);
    }

    /**
     * Update and save user object.
     *
     * @param  stdClass  $user
     * @return void
     */
    protected function updateUser($user)
    {
        DB::table($user->table)
            ->where('email', $user->email)
            ->update([
                'verification_token' => $user->verification_token,
                'verified' => $user->verified
            ]);
    }

    /**
     * Compare the verification token given by the user with the one stored.
     *
     * @param  string  $storedToken
     * @param  string  $requestToken
     * @return bool
     */
    protected function compareToken($storedToken, $requestToken)
    {
        return $storedToken == $requestToken;
    }

    /**
     * Prepare and send the email with the verification token link.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $subject
     * @return mixed
     */
    protected function emailVerificationLink(AuthenticatableContract $user, $subject)
    {
        return $this->mailer->send('emails.user-verification', compact('user'), function ($m) use ($user, $subject) {
            $m->to($user->email);

            $m->subject(is_null($subject) ? 'Your Account Verification Link' : $subject);
        });
    }

    /**
     * Generate the verification token.
     *
     * @param  string  $email
     * @return string
     */
    protected function generateToken($email)
    {
        return sha1(microtime() . Crypt::encrypt($email));
    }

    /**
     * Update and save the model instance with the verification token.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return bool
     *
     * @throws \Jrean\UserVerification\Exceptions\ModelNotCompliantException
     */
    protected function saveToken(AuthenticatableContract $user, $token)
    {
        if (! $this->isCompliant($user)) {
            throw new ModelNotCompliantException();
        }

        $user->verified = false;

        $user->verification_token = $token;

        return $user->save();
    }

    /**
     * Determine if the given model table has the verified and verification_token
     * columns.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return  bool
     */
    protected function isCompliant(AuthenticatableContract $user)
    {
        if (
            $this->hasColumn($user, 'verified')
            && $this->hasColumn($user, 'verification_token')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if the given model talbe has the given column.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $column
     * @return bool
     */
    protected function hasColumn(AuthenticatableContract $user, $column)
    {
        return $this->schema->hasColumn($user->getTable(), $column);
    }

    /**
     * Fetch the user by email.
     *
     * @param  string  $email
     * @param  string  $table
     * @return stdClass
     *
     * @throws \Jrean\UserVerification\Exceptions\UserNotFoundException
     */
    protected function getUserByToken($token, $table)
    {
        $user = DB::table($table)->where('verification_token', $token)->first(['id', 'email', 'verified', 'verification_token', 'table']);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        $user->table = $table;

        return $user;
    }
}
