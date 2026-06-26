import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/sections/instagram.php"
with open(file_path, "r") as f:
    content = f.read()

replacement = """                <div class="flacso-instagram-embed">
                    <?php
                    $feed = class_exists('Flacso_Instagram_API') ? Flacso_Instagram_API::get_feed() : new WP_Error('no_class', 'API class not found');
                    
                    if (is_wp_error($feed)) :
                        // Fallback to static card if error or no token
                    ?>
                    <a href="<?php echo esc_url($profile_url); ?>" target="_blank" rel="noopener noreferrer" class="flacso-instagram-static-card">
                        <div class="flacso-ig-static-icon">
                            <i class="bi bi-instagram"></i>
                        </div>
                        <h3>@flacsouruguay</h3>
                        <span class="flacso-ig-static-btn">Ver perfil &rarr;</span>
                    </a>
                    <?php else : ?>
                        <div class="flacso-instagram-api-feed">
                            <?php foreach ($feed as $item) : 
                                $caption_preview = wp_trim_words($item['caption'], 15);
                            ?>
                                <a href="<?php echo esc_url($item['permalink']); ?>" target="_blank" rel="noopener noreferrer" class="flacso-ig-feed-item">
                                    <div class="flacso-ig-feed-image" style="background-image: url('<?php echo esc_url($item['media_url']); ?>');">
                                        <?php if ($item['media_type'] === 'VIDEO') : ?>
                                            <div class="flacso-ig-feed-type-icon"><i class="bi bi-play-fill"></i></div>
                                        <?php elseif ($item['media_type'] === 'CAROUSEL_ALBUM') : ?>
                                            <div class="flacso-ig-feed-type-icon"><i class="bi bi-images"></i></div>
                                        <?php endif; ?>
                                        <div class="flacso-ig-feed-overlay">
                                            <i class="bi bi-instagram"></i>
                                            <p><?php echo esc_html($caption_preview); ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>"""

content = re.sub(
    r'<div class="flacso-instagram-embed">.*?</div>\s+</div>',
    replacement + "\n            </div>",
    content,
    flags=re.DOTALL
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched instagram.php successfully")
