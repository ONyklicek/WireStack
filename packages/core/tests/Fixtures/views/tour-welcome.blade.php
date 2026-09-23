{{-- An application's own welcome block, as TourWelcome::view() takes one.

     It owns its visibility (`x-show="greeting"`) and calls the two names the
     tour's Alpine scope gives it, which is the whole contract the framework
     promises a view here. --}}
<div data-testid="own-welcome" x-show="greeting" x-cloak>
    <h2>{{ $welcome->getHeading() }}</h2>
    <p>{{ $tour->getId() }}</p>

    <button type="button" x-on:click="later()">later</button>
    <button type="button" x-on:click="begin()">begin</button>
</div>
