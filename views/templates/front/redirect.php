<body onload="document.forms['proxy_form'].submit()">
<form id="proxy_form" action="<?= $redirectUrl ?>"
      method="POST" hidden enctype="application/x-www-form-urlencoded" class="no-display">
    <?php foreach ($params as $name => $value):?>
    <input value="<?= $value ?>" name="<?= $name ?>" type="hidden"/>
    <?php endforeach?>
</form>
</body>
