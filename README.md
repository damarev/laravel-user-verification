**jrean/laravel-user-verification** is a PHP package built for Laravel 5 to
easily handle a user verification flow and validate its email.

## About

- Generate and store a verification token for a registered user.
- Send an email with the verification token link.
- Handle the verification of the token.
- Set the user as verified.

## Installation

This project can be installed via [Composer](http://getcomposer.org).
To get the latest version of Laravel User Verification, simply add the following line to
the require block of your composer.json file:

    "jrean/laravel-user-verification": "dev-master"
    "jrean/laravel-user-verification": "2.*"

or

    "composer require jrean/laravel-user-verification"

You'll then need to run `composer install` or `composer update` to download it and
have the autoloader updated.

### Add the Service Provider

Once Larvel User Verification is installed, you need to register the service provider.

Open up `config/app.php` and add the following to the `providers` key:

* `Jrean\UserVerification\UserVerificationServiceProvider::class`

### Add the Facade/Alias

Open up `config/app.php` and add the following to the `aliases` key:

* `'UserVerification' => Jrean\UserVerification\Facades\UserVerification::class`

## Configuration

Prior to use this package the table representing the user must be updated with
two new columns, "verified" and "verification_token". It is mandatory to add
the two columns on the same table and where the user's email is
stored. Last but not least, the model must implement the authenticatable
interface `Illuminate\Contracts\Auth\Authenticatable` which is the default with
the Eloquent User model.

### Migration

Generate the migration file with the following artisan command:

```
php artisan make:migration add_verification_to_:table_table --table=":table"
```

Where `:table` is replaced by the table of your choice.

For instance if you want to keep the default Eloquent User table:

```
php artisan make:migration add_verification_to_users_table --table="users"
```

Once the migration is generated, edit the file in `database/migration` with the following:

```
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(':table', function (Blueprint $table) {
            $table->boolean('verified')->default(false);
            $table->string('verification_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(':table', function (Blueprint $table) {
            $table->dropColumn('verified');
            $table->dropColumn('verification_token');
        });
    }

```

Where `:table` is replaced by the table name of your choice.

Migrate the migration with the following command:

```
php artisan migrate
```

### Email

This package offers to send an email with a link containing the verification token.

Please refer to the Laravel documentation for the proper email component configuration.

#### View

The user will receive an e-mail with a link leading to the getVerification
method (endpoint). You will need to create a view for this e-mail at
resources/views/emails/user-verification.blade.php. The view will receive the
`$user` variable which contains the user details such as the verification
token. Here is an sample e-mail view to get you started:

```
Click here to verify your account {{ url('auth/verification/' . $user->verification_token) }}
```

## Usage

### API

The package public API offers four (4) methods.

* `generate(AuthenticatableContract $user)`

Generate and save a verification token for the given user.

* `send(AuthenticatableContract $user, $subject = null)`

Send by email a link containing the verification token.

* `process(AuthenticatableContract $user, $token)`

Process the token verification for the given user.

* `isVerified(AuthenticatableContract $user)`

Check if the given user is verified.

### Facade

The package offers a facade callable with `UserVerification::`. You can use it
over the four (4) previous listed public methods.

### Trait

The package also offers a trait for a quick implementation.

#### Endpoints

The two following methods are endpoints you can join by defining the proper
route(s) of your choice.

* `getVerification($token)`

Handle the user verification. It requires a string parameter representing the
verification token to verify.

* `getVerificationError()`

Do something if the verification fails.

#### Custom attributes/properties

To customize the package behaviour and the redirects you can implement and
customize five (6) attributes/properties:

* `$redirectIfVerified = '/';`

Where to reditect if the authenticated user is already verified.

* `$redirectAfterTokenGeneration = '/';`

Where to redirect after a successful verification token generation.

* `$redirectAfterVerification = '/';`

Where to redirect after a successful verification token verification.

* `$redirectIfVerificationFails = '/auth/verification/error';`

Where to redirect after a failling token verification.

* `$verifiationErrorView = 'errors.user-verification';`

Name of the view returned by the getVerificationError method.

* `$userTable = 'users';`

Name of the default table used for managing users.

## Example

This package whishes to let you be creative while offering you a predefined
path. The following sample assume you have configured Laravel for the
package as well as created and migrated the migration according to this
documentation.
This package doesn't require the user to be authenticated to perform the
verification. You are free to implement any flow you may want to achieve.
Note that by default the behaviour of Laravel is to return an authenticated
user straight after the registration.

### Example

The following code sample aims to showcase a quick and basic implementation.

Edit the `app\Http\Controller\Auth\AuthController.php` file.

- Import the `VerifiesUsers` trait (mandatory)
- Overwrite and customize the redirect path attributes/properties (not
    mandatory)
- Overwrite and customize the view name for the getVerificationError method
    (not mandatory)
- Create the verification error view according to the defined path (mandatory)
- Overwrite the contructor (not mandatory)
- Overwrite the postRegister/register method (mandatory)

```
    ...

    use Jrean\UserVerification\Traits\VerifiesUsers;
    use Jrean\UserVerification\Facades\UserVerification;

    ...

    use VerifiesUsers;

    ...

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Based on the workflow you want you may update the following lines.

        // Laravel 5.0.*|5.1.*
        $this->middleware('guest', ['except' => ['getLogout']]);

        // Laravel 5.2.*
        $this->middleware('guest', ['except' => ['logout']]);
    }

    ...

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function postRegister(Request $request)
    {
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            $this->throwValidationException(
                $request, $validator
            );
        }

        $user = $this->create($request->all());

        // Authenticating the user is not mandatory at all.
        //Auth::login($user);

        UserVerification::generate($user);

        UserVerification::send($user, 'My Custom Email Subject');

        return redirect($this->redirectPath());
    }
```

Edit the `app\Http\routes.php` file.

- Define two new routes

```
    Route::get('auth/verification/error', 'Auth\AuthController@getVerificationError');
    Route::get('auth/verification/{token}', 'Auth\AuthController@getVerification');

    or

    Route::get('verification/error', 'Auth\AuthController@getVerificationError');
    Route::get('verification/{token}', 'Auth\AuthController@getVerification');
```

## Contribute

This package is (yet) under development and refactoring but is ready for
production. Please, feel free to comment, contribute and help. I will be happy
to get some help to deliver tests.

## License

Laravel User Verification is licensed under [The MIT License (MIT)](LICENSE).
