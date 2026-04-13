@foreach($names as $name)
@inline('partials.greeting', ['name' => $name])
@endforeach