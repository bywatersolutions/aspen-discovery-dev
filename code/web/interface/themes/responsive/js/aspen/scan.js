// To use Html5QrcodeScanner (more info below)
//import {Html5QrcodeScanner} from "https://unpkg.com/html5-qrcode";

let scanner = null;
function onScanSuccess(decodedText, decodedResult) {
  // handle the scanned code as you like, for example:
  console.log(`Code matched = ${decodedText}`, decodedResult);
  console.log(`/AspenPWA/AJAX?method=checkoutItem&barcode=${decodedText}`);
}

function onScanFailure(error) {
  // handle scan failure, usually better to ignore and keep scanning.
  // for example:
  console.warn(`Code scan error = ${error}`);
}

function startScanner()
{
  console.log("in startScanner");
  $("#scan-session").hide();
  $("#scan-back").show();
  scanner = new Html5QrcodeScanner(
    "reader",
    { fps: 10, qrbox: {width: 250, height: 250} },
    /* verbose= */ false);
  scanner.render(onScanSuccess, onScanFailure);
}

function closeScanner()
{
  scanner.clear();
  $("#scan-back").hide();
  $("#scan-session").show();
}
function startType()
{
  console.log("in startType");
  //Do we want to clear this?
  $("#type-barcode-input").val("");
  $("#scan-session").hide();
  $(".type-panel").show();

}
function closeType()
{
  $("#scan-session").show();
  $(".type-panel").hide();
}

function startSession()
{
  sessionCheckouts = [];
  refreshCheckoutList();
  $("#scan-session").show();
  $("#finish-panel").hide();
}

function finishSession()
{
  $("#scan-session").hide();
  $("#finish-panel").show();
}

function submitBarcode()
{
  let barcode = $("#type-barcode-input").val();
  
  $("#scan-session").show();
  $(".type-panel").hide();

  let url = `/AspenPWA/AJAX?method=checkoutItem&barcode=${barcode}`;
  console.log(url);
  $.getJSON(url, function (data) {
    console.log("response...");
    console.log(data);
    if(data.success)
    {
      sessionCheckouts.push(barcode);
      refreshCheckoutList();
    }
    else {
      //TODO show a proper modal or toast here.
      console.log("error");
      console.log(data.title);
      console.log(data.message);
    }
  });
}
function refreshCheckoutList()
{
  let checkoutList = $("#scan-session ul");
  checkoutList.empty();
  if(sessionCheckouts.length == 0)
  {
    const $li = $("<li></li>").text(emptySessionText);
    checkoutList.append($li);
    return;
  }
  $.each(sessionCheckouts, function(index, value) {
    const $li = $("<li></li>").text(value);
    checkoutList.append($li);
  });
}

function viewCheckouts()
{
  ///MyAccount/CheckedOut?source=all
  navigation.navigate("/MyAccount/CheckedOut");
}
let sessionCheckouts = [];
let emptySessionText = $("#scan-session ul li").text();
$("#scan-start").click(startScanner);
$("#type-start").click(startType);
$("#type-barcode-submit").click(submitBarcode);
$("#scan-back").click(closeScanner);
$("#type-back").click(closeType);
$("#finish-scan-session").click(finishSession);
$("#new-scan-session").click(startSession);
$("#view-checkouts").click(viewCheckouts);

refreshCheckoutList();