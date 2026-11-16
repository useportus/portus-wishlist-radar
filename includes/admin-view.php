<?php
defined( 'ABSPATH' ) || exit;
$portus_wr_enabled = (bool) get_option( 'portus_wr_alerts_enabled', 1 );
$portus_wr_pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE email != '' AND confirmed = 0", $portus_wr_table ) );
$portus_wr_people = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT owner) FROM %i", $portus_wr_table ) );
$portus_wr_max_count = $portus_wr_top ? max( 1, (int) $portus_wr_top[0]->total ) : 1;
$portus_wr_theme_name = wp_get_theme()->get( 'Name' );
$portus_wr_summary_cards = apply_filters( 'portus_wr_admin_summary_cards', array(
 array( 'label' => __( 'Versão', 'portus-wishlist-radar' ), 'value' => PORTUS_WR_VERSION, 'description' => __( 'Instalada neste site', 'portus-wishlist-radar' ) ),
 array( 'label' => __( 'Edição', 'portus-wishlist-radar' ), 'value' => 'Free', 'description' => __( 'Lista de desejos para sua loja', 'portus-wishlist-radar' ) ),
 array( 'label' => __( 'Licença', 'portus-wishlist-radar' ), 'value' => __( 'Não necessária', 'portus-wishlist-radar' ), 'description' => __( 'A versão Free funciona sem chave', 'portus-wishlist-radar' ) ),
 array( 'label' => __( 'Tema', 'portus-wishlist-radar' ), 'value' => $portus_wr_theme_name ?: __( 'Não identificado', 'portus-wishlist-radar' ), 'description' => __( 'Tema ativo nesta loja', 'portus-wishlist-radar' ) ),
) );
?>
<div class="wrap pwr-admin">
 <div class="pwr-admin-brand">
  <img class="pwr-admin-logo" src="<?php echo esc_url( plugins_url( 'assets/admin-logo.png', PORTUS_WR_FILE ) ); ?>" width="60" height="60" alt="" />
  <div><h1>Portus Wishlist Radar</h1><p><?php esc_html_e( 'Gerencie favoritos, acompanhe o interesse dos clientes e crie novas oportunidades de venda.', 'portus-wishlist-radar' ); ?></p></div>
 </div>
 <section class="pwr-admin-summary" aria-label="<?php esc_attr_e( 'Informações do plugin', 'portus-wishlist-radar' ); ?>">
  <?php foreach ( $portus_wr_summary_cards as $portus_wr_card ) : ?>
   <div class="pwr-admin-summary-card"><span><?php echo esc_html( $portus_wr_card['label'] ); ?></span><strong><?php echo esc_html( $portus_wr_card['value'] ); ?></strong><p><?php echo esc_html( $portus_wr_card['description'] ); ?></p></div>
  <?php endforeach; ?>
 </section>
 <?php do_action( 'portus_wr_admin_navigation' ); ?>
 <?php if ( apply_filters( 'portus_wr_admin_show_overview', true ) ) : ?>
 <header class="pwr-admin-hero">
  <div><span class="pwr-admin-eyebrow">PORTUS · WISHLIST RADAR</span><h2>O desejo dos seus clientes,<br>mais perto da próxima venda.</h2><p>Acompanhe os favoritos da loja e os alertas dos seus produtos.</p></div>
  <a class="pwr-admin-link" href="<?php echo esc_url( get_permalink( get_option( 'portus_wr_page_id' ) ) ); ?>" target="_blank" rel="noopener">Ver lista na loja ↗</a>
 </header>
 <div class="pwr-metrics">
 <?php foreach ( array(
  array( 'Itens salvos', $portus_wr_stats->total, 'Produtos e variações nas listas atuais', 'heart' ),
  array( 'Listas com produtos', $portus_wr_people, 'Visitantes e clientes com favoritos', 'heart' ),
  array( 'Alertas confirmados', $portus_wr_stats->confirmed, $portus_wr_pending . ' aguardando confirmação por e-mail', 'bell' ),
  array( 'Envios aceitos', $portus_wr_stats->sent, 'Aceitos pelo serviço de e-mail da loja', 'arrow' ),
 ) as $portus_wr_metric ) : ?>
  <section class="pwr-metric"><div><span><?php echo esc_html( $portus_wr_metric[0] ); ?></span><?php echo wp_kses( self::icon( $portus_wr_metric[3] ), self::svg_allowed() ); ?></div><strong><?php echo esc_html( number_format_i18n( $portus_wr_metric[1] ) ); ?></strong><p><?php echo esc_html( $portus_wr_metric[2] ); ?></p></section>
 <?php endforeach; ?>
 </div>
 <div class="pwr-admin-columns">
  <section class="pwr-admin-panel pwr-ranking"><header><div><h2>Os mais desejados</h2><p>Os produtos que estão chamando atenção.</p></div><span class="pwr-admin-tag">Top 20</span></header>
   <?php if ( $portus_wr_top ) : ?>
   <div class="pwr-table-scroll"><table><thead><tr><th scope="col">Produto / variação</th><th scope="col">Interesse</th><th scope="col">Favoritos</th></tr></thead><tbody>
   <?php foreach ( $portus_wr_top as $portus_wr_index => $portus_wr_item ) : $portus_wr_p = wc_get_product( $portus_wr_item->product_id ); ?>
    <tr><td><div class="pwr-admin-product"><span class="pwr-rank"><?php echo esc_html( str_pad( $portus_wr_index + 1, 2, '0', STR_PAD_LEFT ) ); ?></span><?php if ( $portus_wr_p ) { echo wp_kses_post( $portus_wr_p->get_image( array( 44, 44 ) ) ); } ?><div><?php if ( $portus_wr_p ) : ?><a href="<?php echo esc_url( get_edit_post_link( $portus_wr_p->is_type( 'variation' ) ? $portus_wr_p->get_parent_id() : $portus_wr_p->get_id() ) ); ?>"><?php echo esc_html( $portus_wr_p->get_name() ); ?></a><?php else : ?><?php echo esc_html( 'Produto removido #' . $portus_wr_item->product_id ); ?><?php endif; ?><small>#<?php echo esc_html( $portus_wr_item->product_id ); ?></small></div></div></td><td><div class="pwr-interest" aria-hidden="true"><span style="width:<?php echo esc_attr( round( 100 * $portus_wr_item->total / $portus_wr_max_count ) ); ?>%"></span></div></td><td><strong><?php echo esc_html( $portus_wr_item->total ); ?></strong></td></tr>
   <?php endforeach; ?>
   </tbody></table></div>
   <?php else : ?>
    <div class="pwr-admin-empty"><?php echo wp_kses( self::icon(), self::svg_allowed() ); ?><h3>As próximas descobertas aparecem aqui</h3><p>Quando alguém salvar um produto, você verá quais itens despertam mais interesse.</p></div>
   <?php endif; ?>
   <footer>Os números refletem as listas atuais. Excluir um favorito também remove seu histórico de envios.</footer>
  </section>
  <aside class="pwr-admin-sidebar">
   <section class="pwr-admin-panel"><header><div><h2>Alertas por e-mail</h2><p>Um motivo para o cliente voltar.</p></div></header>
    <?php if ( ! PORTUS_WR_Plugin::has_pro() ) : ?>
    <div class="pwr-admin-settings"><span class="pwr-admin-status">Recurso Pro</span><p>Ative o complemento Portus Wishlist Radar Pro para enviar alertas de preço e reposição. Os favoritos e as inscrições existentes ficam preservados.</p><a href="https://useportus.com/portus-wishlist-radar-pro/" target="_blank" rel="noopener noreferrer">Conhecer a versão Pro →</a></div>
    <?php else : ?>
    <?php do_action( 'portus_wr_render_alerts_admin', $portus_wr_enabled ); ?>
    <?php endif; ?>
   </section>
   <section class="pwr-admin-panel pwr-admin-setup"><header><div><h2>Sua lista na loja</h2><p>Adicione a página ao menu da loja.</p></div></header><p>Para exibir a lista em outra página, use:</p><code>[portus_wishlist]</code><a href="<?php echo esc_url( get_permalink( get_option( 'portus_wr_page_id' ) ) ); ?>">Abrir página de favoritos →</a></section>
  </aside>
 </div>
 <?php include __DIR__ . '/settings-view.php'; do_action( 'portus_wr_render_reports' ); ?>
 <details class="pwr-admin-notes"><summary>Como funcionam os envios e os indicadores</summary><p>Processamento via WP-Cron em lotes de até 100 inscrições a cada cinco minutos. Configure um cron real para lojas com pouco tráfego. Cada inscrição recebe no máximo um alerta a cada 24 horas. Envios aceitos não comprovam entrega na caixa de entrada.</p></details>
 <?php endif; do_action( 'portus_wr_admin_extension_panel' ); ?>
</div>
