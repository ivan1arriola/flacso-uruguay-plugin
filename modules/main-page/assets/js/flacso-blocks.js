(function (blocks, element, components, blockEditor, serverSideRender, data) {
    if (!blocks || !serverSideRender || !data || !Array.isArray(data.blocks)) {
        return;
    }

    const { registerBlockType } = blocks;
    const { Fragment, createElement: el } = element;
    const { PanelBody, TextControl, ToggleControl } = components;
    const { InspectorControls } = blockEditor || wp.editor;
    const ServerSideRender = serverSideRender;

    const renderControl = (attrKey, attrConfig, attributes, setAttributes) => {
        const label = attrConfig.label || attrKey;
        const type = attrConfig.type || 'string';
        const value = attributes[attrKey];

        if (type === 'boolean') {
            return el(ToggleControl, {
                label,
                checked: !!value,
                onChange: (newValue) => setAttributes({ [attrKey]: !!newValue }),
            });
        }

        const inputType = type === 'number' ? 'number' : 'text';
        return el(TextControl, {
            label,
            type: inputType,
            value: value ?? '',
            onChange: (newValue) => {
                let parsed = newValue;
                if (type === 'number') {
                    parsed = newValue === '' ? '' : Number(newValue);
                }
                setAttributes({ [attrKey]: parsed });
            },
        });
    };

    data.blocks.forEach((block) => {
        registerBlockType(block.name, {
            title: block.title,
            description: block.description,
            icon: block.icon,
            category: block.category || 'widgets',
            keywords: block.keywords || [],
            supports: block.supports || {},
            attributes: block.attributes || {},
            edit: (props) => {
                const { attributes, setAttributes } = props;
                const controls = block.attributes
                    ? Object.entries(block.attributes).map(([key, config]) =>
                          renderControl(key, config, attributes, setAttributes)
                      )
                    : [];

                return el(
                    Fragment,
                    {},
                    controls.length
                        ? el(
                              InspectorControls,
                              {},
                              el(PanelBody, { title: block.title, initialOpen: true }, controls)
                          )
                        : null,
                    el(ServerSideRender, { block: block.name, attributes })
                );
            },
            save: () => null,
        });
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor || window.wp.editor, window.wp.serverSideRender, window.flacsoShortcodeBlocks || {});
