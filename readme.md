# Elastic

This is where your description should go. Take a look at [contributing.md](contributing.md) to see a to do list.

## Installation

Via Composer

``` bash
$ composer require exdeliver/elastic
```

## Usage

Create a resource and define the data to be imported by resources.

    php artisan make:resource PhoneNumberResource


    <?php
    
    namespace App\Http\Resources;
    
    use App\Models\PhoneNumber;
    use Exdeliver\Elastic\Resources\ElasticSearchResource;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Http\Request;
    
    class PhoneNumberResource extends ElasticSearchResource
    {
        public const ELASTIC_INDEX = 'phone_numbers';
    
        public static function model(): Model
        {
            return new PhoneNumber();
        }
    
        public function toElastic(Request $request): array
        {
            /**
             * Data from the model that should be imported
             */
            return [
                'index' => self::ELASTIC_INDEX,
                'body' => [
                    'uuid' => $this->uuid,
                    'number' => $this->phonenumber,
                    'contact' => ContactResource::make($this->contact), // supports make() and collection()
                ],
            ];
        }
    
        public static function elastic(): array
        {
            return [
                'index' => self::ELASTIC_INDEX,
            ];
        }
    }

    Your controllewr

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email author@email.com instead of using the issue tracker.

## Credits

- [Author Name][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[link-author]: https://github.com/exdeliver
[link-contributors]: ../../contributors
# elastic
