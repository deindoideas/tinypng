{*
* 2007-2015 Deindo
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    Deindo Ideas SL <contacto@deindo.es>
*  @copyright 2007-2019 Deindo Ideas SL
*  @license   http://www.deindo.es
*}

{literal}<script type="text/javascript">
    var ruta_modulos = "{/literal}{$ruta_modulos|escape:'htmlall':'UTF-8'}{literal}";
    var adminimages = "{/literal}{$link_adminimages|escape:'htmlall':'UTF-8'}{literal}";
    var prod_images = {/literal}[{$prod_images}]{literal};
    var i = 0;
    var procesados = [];
</script>{/literal}

<script type="text/javascript">

    function imageCompression()
    {
        imagen = prod_images.pop();
        
        if (procesados.indexOf(prod_images) == -1) {

            if (imagen === undefined || imagen.length == 0) {
                $('#imported').html('');
                $('#imported').append('<p><h2>Finished. Remember to regenerate thumbnails on:</h2></p>');
                $('#imported').append('<p><a href="'+adminimages+'">Regenerate thumbnails now</a>');
                return;
            } else {
                $('#imported').append('<p><h3>Image (' + imagen + ') sending to compression ...</h3></p>');
            }

            $.ajax({
                url: ruta_modulos,
                type: "POST",
                data: 'c='+imagen,
                dataType: 'JSON',
                success: function(r) {
                    i++;                    
                    $('#imported').append('<p><h3>Image ' + imagen + ' result: </h3></p>');
                    $('#imported').append('Input image size: '+r.input.size+'<br/>');
                    $('#imported').append('Output image size: '+r.output.size+'<br/>');
                    $('#imported').append('Compression ratio : '+r.output.ratio+'<br/><br/>');
                    $('#num_image').html(i);
                    procesados.push(imagen);
                    setTimeout(function(){ imageCompression(); }, 100);
                    }
                });

        } else {

            if (imagen === undefined || imagen.length == 0) {
                $('#imported').html('');
                $('#imported').append('<p><h2>Finished. Remember to regenerate thumbnails on:</h2></p>');
                $('#imported').append('<p><a href="'+adminimages+'">Regenerate thumbnails now</a>');
                return;
            }

           	setTimeout(function(){ imageCompression(); }, 100);
        }
    }
    
    $(document).on('click', '[name="submitCustomers"]', function(e){
    	e.preventDefault();
        imageCompression();
    });

</script>

<div id="#imported"></div>

<div class="panel" id="fieldset_0_1">
												
	<div class="panel-heading">
		<i class="icon-group"></i> Bulk results <span id="num_image">0</span> to {$images_to_opt|escape:'htmlall':'UTF-8'} images to optimize
	</div>
					
								
	<div class="form-wrapper">
											
		<div class="form-group">

                <p> When you finished to opimize images remember to regenerate thumnails. You can do it here: <a href="{$link_adminimages|escape:'htmlall':'UTF-8'}">{$link_adminimages|escape:'htmlall':'UTF-8'}</a>
													
				<div id="imported"></div>
							
		</div>
						
										
																	
	</div><!-- /.form-wrapper -->			
</div>