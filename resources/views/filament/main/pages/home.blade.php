<x-filament-panels::page>
    {{-- The page's own assets, built like every other one, loaded only here: the panel serves all
         the public pages, and none of the rest of them wants a photograph or a pane at 30vh. --}}
    @vite(['resources/css/markdown.css', 'resources/css/home.css', 'resources/js/home.js'])

    {{-- Data, not markup: the script reads it and builds the surfaces it crossfades between. Its
         presence is also how the script knows it is on the home page. --}}
    <script type="application/json" id="wallpapers">@json($this->wallpapers())</script>

    {{-- The glass and the markdown styles come from the panel's own stylesheets, so the classes
        are all this page needs to add. --}}
    <div class="glass prose home-pane">
        {!! $this->getContentHtml() !!}
    </div>
</x-filament-panels::page>
