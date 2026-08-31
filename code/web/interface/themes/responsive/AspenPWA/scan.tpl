<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<!-- https://scanapp.org/html5-qrcode-docs/docs/intro -->
<script src="/AspenPWA/js" type="module" type="text/javascript"></script>
  <div class="scan-page center">
  <button style="display: none;" id="scan-back">
    X
  </button>
  <div id="reader" class="scan-panel" width="600px">

  </div>
  <div class="type-panel" style="display: none;">
    <input id="type-barcode-input" type="text"/>
    <button id="type-back">Close</button>
    <button id="type-barcode-submit">Add new Item</button>
  </div>

  <div id="scan-session">
    <p>{{$linked}}{{$hasLinkedUsers}}</p>
    <p>Scan & Go Banner</p>
    <p>You are checking out as {{$displayName}}</p>
    <p>Add a new item</p>
    <div>
      <span>
        <button id="scan-start">
          scan
        </button>
      </span>
      &nbsp;
      <span>
        <button id="type-start">
          type
        </button>
      </span>
    </div>
    <p>Items checked out during this session...</p>
    <ul id="session-items">
      <li>No items checked out yet</li>
    </ul>
    <p><button id="finish-scan-session">finish</button></p>
  </div>
  <div id="finish-panel" style="display: none;">
  Session Finished

  You can start a new checkout session or view your checkouts
  <button id="new-scan-session">Start new Session</button> <button id="view-checkouts">View Checkouts</button>
  </div>
</scan>

<!-- https://github.com/mebjas/html5-qrcode -->