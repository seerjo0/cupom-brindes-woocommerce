<?php
/**
 * Plugin Name: SGDevs - Cupons com Produtos Grátis e Mensagens Personalizadas
 * Description: Adiciona produtos gratuitos e mensagens personalizadas quando cupons são aplicados no WooCommerce
 * Version: 1.0.0
 * Author: SGDevs
 * Author URI: https://www.sgdevs.com.br
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sgdevs-cupons-com-produtos-gratis-e-mensagens-personalizadas
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

defined('ABSPATH') || exit;

/**
 * =============================================
 * SEÇÃO DE PRODUTOS GRÁTIS COM CUPOM
 * =============================================
 */

/**
 * Adiciona metabox para produtos gratuitos em cupons
 * Adds metabox for free products in coupons
 */
add_action('add_meta_boxes', 'sgdevs_adicionar_metabox_produto_gratis_cupom');

function sgdevs_adicionar_metabox_produto_gratis_cupom() {
    add_meta_box(
        'sgdevs_wc_coupon_produto_gratis',
        'Produto Grátis com Cupom',
        'sgdevs_exibir_metabox_produto_gratis',
        'shop_coupon',
        'side',
        'default'
    );
}

function sgdevs_exibir_metabox_produto_gratis($post) {
    $produto_gratis_id = get_post_meta($post->ID, '_sgdevs_produto_gratis_id', true);
    
    wp_nonce_field('sgdevs_salvar_produto_gratis_cupom', 'sgdevs_produto_gratis_nonce');
    
    echo '<label for="sgdevs_produto_gratis_id">Selecione o produto grátis:</label>'; // Select the free product
    echo '<select id="sgdevs_produto_gratis_id" name="sgdevs_produto_gratis_id" class="wc-product-search" style="width:100%;" data-placeholder="' . esc_attr__('Procure um produto...', 'woocommerce') . '" data-action="woocommerce_json_search_products_and_variations">';
    
    if ($produto_gratis_id) {
        $product = wc_get_product($produto_gratis_id);
        if (is_object($product)) {
            echo '<option value="' . esc_attr($produto_gratis_id) . '" selected="selected">' . wp_kses_post($product->get_formatted_name()) . '</option>';
        }
    }
    
    echo '</select>';
    echo '<p class="description">Este produto será adicionado automaticamente ao carrinho quando o cupom for aplicado.</p>'; // This product will be automatically added to the cart when the coupon is applied
}

/**
 * Salva os dados do metabox de produto grátis
 * Saves the free product metabox data
 */
add_action('save_post', 'sgdevs_salvar_metabox_produto_gratis');

function sgdevs_salvar_metabox_produto_gratis($post_id) {
    if (!isset($_POST['sgdevs_produto_gratis_nonce']) || !wp_verify_nonce($_POST['sgdevs_produto_gratis_nonce'], 'sgdevs_salvar_produto_gratis_cupom')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if ('shop_coupon' !== $_POST['post_type']) {
        return;
    }
    
    if (isset($_POST['sgdevs_produto_gratis_id'])) {
        update_post_meta($post_id, '_sgdevs_produto_gratis_id', absint($_POST['sgdevs_produto_gratis_id']));
    } else {
        delete_post_meta($post_id, '_sgdevs_produto_gratis_id');
    }
}

/**
 * Adiciona o produto grátis quando o cupom é aplicado
 * Adds the free product when the coupon is applied
 */
add_action('woocommerce_applied_coupon', 'sgdevs_adicionar_produto_gratuito_por_cupom');

function sgdevs_adicionar_produto_gratuito_por_cupom($coupon_code) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    $coupon = new WC_Coupon($coupon_code);
    $coupon_id = $coupon->get_id();
    
    if (!$coupon_id) {
        return;
    }
    
    $produto_gratis_id = get_post_meta($coupon_id, '_sgdevs_produto_gratis_id', true);
    
    if (!$produto_gratis_id) {
        return;
    }
    
    // Verifica se o produto já está no carrinho
    // Checks if the product is already in the cart
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if ($cart_item['product_id'] == $produto_gratis_id) {
            return;
        }
    }
    
    // Adiciona o produto ao carrinho com preço zero
    // Adds the product to the cart with zero price
    WC()->cart->add_to_cart($produto_gratis_id, 1, 0, array(), array(
        'preco_original' => wc_get_product($produto_gratis_id)->get_price()
    ));
}

/**
 * Define o preço zero para o produto grátis
 * Sets zero price for the free product
 */
add_action('woocommerce_before_calculate_totals', 'sgdevs_definir_preco_zero_produto_gratis', 10, 1);

function sgdevs_definir_preco_zero_produto_gratis($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['preco_original'])) {
            $cart_item['data']->set_price(0);
        }
    }
}

/**
 * Remove o produto grátis se o cupom for removido
 * Removes the free product if the coupon is removed
 */
add_action('woocommerce_removed_coupon', 'sgdevs_remover_produto_gratis_ao_remover_cupom', 10, 1);

function sgdevs_remover_produto_gratis_ao_remover_cupom($coupon_code) {
    $coupon = new WC_Coupon($coupon_code);
    $coupon_id = $coupon->get_id();
    
    if (!$coupon_id) {
        return;
    }
    
    $produto_gratis_id = get_post_meta($coupon_id, '_sgdevs_produto_gratis_id', true);
    
    if (!$produto_gratis_id) {
        return;
    }
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if ($cart_item['product_id'] == $produto_gratis_id) {
            WC()->cart->remove_cart_item($cart_item_key);
            break;
        }
    }
}

/**
 * =============================================
 * SEÇÃO DE MENSAGENS PERSONALIZADAS PARA CUPONS
 * SECTION FOR CUSTOM COUPON MESSAGES
 * =============================================
 */

/**
 * Adiciona metabox para mensagem personalizada
 * Adds metabox for custom message
 */
add_action('add_meta_boxes', 'sgdevs_adicionar_metabox_mensagem_cupom');

function sgdevs_adicionar_metabox_mensagem_cupom() {
    add_meta_box(
        'sgdevs_wc_coupon_mensagem_personalizada',
        'Mensagem Personalizada ao Aplicar Cupom',
        'sgdevs_exibir_metabox_mensagem_cupom',
        'shop_coupon',
        'normal',
        'default'
    );
}

function sgdevs_exibir_metabox_mensagem_cupom($post) {
    $mensagem_cupom = get_post_meta($post->ID, '_sgdevs_mensagem_cupom', true);
    wp_nonce_field('sgdevs_salvar_mensagem_cupom', 'sgdevs_mensagem_cupom_nonce');
    
    echo '<label for="sgdevs_mensagem_cupom">Mensagem que será exibida quando o cupom for aplicado:</label>'; // Message that will be displayed when the coupon is applied
    echo '<textarea id="sgdevs_mensagem_cupom" name="sgdevs_mensagem_cupom" rows="4" style="width:100%;">' . esc_textarea($mensagem_cupom) . '</textarea>';
    echo '<p class="description">Esta mensagem substituirá a mensagem padrão do WooCommerce quando o cupom for aplicado.</p>'; // This message will replace the default WooCommerce message when the coupon is applied
}

/**
 * Salva os dados do metabox de mensagem
 * Saves the custom message metabox data
 */
add_action('save_post', 'sgdevs_salvar_metabox_mensagem_cupom');

function sgdevs_salvar_metabox_mensagem_cupom($post_id) {
    if (!isset($_POST['sgdevs_mensagem_cupom_nonce']) || !wp_verify_nonce($_POST['sgdevs_mensagem_cupom_nonce'], 'sgdevs_salvar_mensagem_cupom')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if ('shop_coupon' !== $_POST['post_type']) {
        return;
    }
    
    if (isset($_POST['sgdevs_mensagem_cupom'])) {
        update_post_meta($post_id, '_sgdevs_mensagem_cupom', sanitize_textarea_field($_POST['sgdevs_mensagem_cupom']));
    } else {
        delete_post_meta($post_id, '_sgdevs_mensagem_cupom');
    }
}

/**
 * Exibe a mensagem personalizada quando o cupom é aplicado
 * Displays the custom message when the coupon is applied
 */
add_filter('woocommerce_coupon_message', 'sgdevs_exibir_mensagem_personalizada_cupom', 10, 3);

function sgdevs_exibir_mensagem_personalizada_cupom($msg, $msg_code, $coupon) {
    if ($msg_code === WC_Coupon::WC_COUPON_SUCCESS) {
        $coupon_id = $coupon->get_id();
        $mensagem_personalizada = get_post_meta($coupon_id, '_sgdevs_mensagem_cupom', true);
        
        if (!empty($mensagem_personalizada)) {
            return $mensagem_personalizada . '<div style="text-align:right;font-size:0.8em;margin-top:5px;">Desenvolvido por SGDevs</div>';
        }
    }
    
    return $msg;
}

/**
 * Adiciona estilo CSS para as mensagens
 * Adds CSS style for the messages
 */
add_action('wp_head', 'sgdevs_adicionar_estilo_mensagens_cupom');

function sgdevs_adicionar_estilo_mensagens_cupom() {
    echo '<style>
        /* Mensagens de cupom aplicado/removido */
        /* Coupon applied/removed messages */
        .woocommerce-message.custom-coupon-message,
        .woocommerce-message[role="alert"] {
            text-align: left !important;
            justify-content: flex-start !important;
            padding-left: 15px !important;
            background-color: #d7f0e0;
            border-color: #46b450;
            color: #1d2327;
            font-size: 1.1em;
        }
        
        /* Remove o ícone de flex que pode estar causando o alinhamento errado */
        /* Removes the flex icon that may be causing misalignment */
        .woocommerce-message::before {
            margin-right: 10px !important;
        }
        
        /* Assinatura SGDevs */
        /* SGDevs signature */
        .woocommerce-message .sgdevs-signature {
            display: block;
            text-align: right;
            font-size: 0.8em;
            margin-top: 5px;
            color: #666;
        }
    </style>';
}

/**
 * Adiciona link para documentação nos plugins instalados
 * Adds documentation link to installed plugins
 */
add_filter('plugin_row_meta', 'sgdevs_add_plugin_row_meta', 10, 2);

function sgdevs_add_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $row_meta = array(
            'docs' => '<a href="https://www.sgdevs.com.br/docs" target="_blank">Documentação</a>', // Documentation
            'support' => '<a href="https://www.sgdevs.com.br/suporte" target="_blank">Suporte</a>' // Support
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}