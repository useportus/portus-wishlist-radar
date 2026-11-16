<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="pwr-settings-grid">
 <section class="pwr-admin-panel"><header><div><h2>Botão e aparência</h2><p>Personalize o botão sem editar o tema.</p></div></header>
  <form method="post" action="options.php" class="pwr-settings-form">
   <?php settings_fields( 'portus_wr_appearance_settings' ); ?>
   <label>Cor principal<input type="color" name="portus_wr_color" value="<?php echo esc_attr( get_option( 'portus_wr_color', '#090d3c' ) ); ?>"></label>
   <label>Formato<select name="portus_wr_button_shape"><?php foreach ( array( 'rounded' => 'Cantos arredondados', 'pill' => 'Pílula', 'square' => 'Cantos retos' ) as $portus_wr_key => $portus_wr_label ) { echo '<option value="' . esc_attr( $portus_wr_key ) . '"' . selected( get_option( 'portus_wr_button_shape', 'rounded' ), $portus_wr_key, false ) . '>' . esc_html( $portus_wr_label ) . '</option>'; } ?></select></label>
   <label>Conteúdo<select name="portus_wr_button_format"><option value="label" <?php selected( get_option( 'portus_wr_button_format', 'label' ), 'label' ); ?>>Coração e texto</option><option value="icon" <?php selected( get_option( 'portus_wr_button_format' ), 'icon' ); ?>>Somente coração</option></select></label>
   <label>Posição<select name="portus_wr_button_position"><option value="after" <?php selected( get_option( 'portus_wr_button_position', 'after' ), 'after' ); ?>>Depois da compra / conteúdo do card</option><option value="before" <?php selected( get_option( 'portus_wr_button_position' ), 'before' ); ?>>Antes da compra / conteúdo do card</option></select></label>
   <p>O posicionamento final também depende da estrutura do tema. Shortcodes continuam disponíveis para posicionamento manual.</p>
   <?php submit_button( 'Salvar aparência', 'primary', 'submit', false ); ?>
  </form>
 </section>
 <?php do_action( 'portus_wr_render_email_settings' ); ?>
</div>
<?php do_action( 'portus_wr_render_retry_settings' ); ?>
