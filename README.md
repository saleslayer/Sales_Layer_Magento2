<h1><a href="https://saleslayer.com/" title="Title">Sales Layer</a> Magento 2 plugin</h1>
Sales Layer plugin allows you to easily synchronize your catalogue information with Magento 2.

<p>
	<h2>Important Notes</h2>
	Please check the important notes for the installation available at https://support.saleslayer.com/magento/important-notes-about-magento-connector (In some cases, a Sales Layer account might be needed to access the documentation).
</p>

<h2>How To Start</h2>

<p>
    <h3>1. Install the package in Magento 2</h3>
    <ul>
        <li>Uncompress module into Magento 2 root folder 'app/code'</li>
        <li>From Magento 2 root folder execute commands:
           <ul>
             <li>php bin/magento setup:upgrade</li>
	           <li>php bin/magento setup:di:compile (if there's an error with 'var/di/' folder just delete it and execute this command again)</li>
             <li>php bin/magento setup:static-content:deploy</li>
             <li>php bin/magento cache:clean</li>
          </ul>
        </li>
        <li>After executing the commands, Sales Layer module will be installed</li>
    </ul>
</p>

<p>
    <h3>2. Create a Sales Layer Magento connector and map the fields</h3>
    <ul>
        <li>The plugin needs the connector ID code and the private key, you will find them in the connector details of Sales Layer.</li>
    </ul>
</p>
    
<p>
    <h3>3. Add the connector credencials in Magento 2</h3>
    <ul>
        <li>Go to Sales Layer -> Import -> Add connector and add the connector id and secret key.</li>
        <li>Finally, In Sales Layer -> Import -> The connector you created, push Synchronize Connector to import categories, products and product formats automatically.</li>
    </ul>
</p>

<p>
    <h2>Requirements for synchronization</h2>
    <ul>
        <li>cUrl extension installed; In order to call and obtain the information from Sales Layer.</li>
        <li>Define the fields relationship in the Sales Layer Magento 2 connector:
            <ul>
                <li>Most Magento 2 fields are already defined in each section, extra fields for products or variants will be Stores -> Attributes -> Product and they must have been created in Magento 2 in order to synchronize.</li>
                <li>When synchronizing a product with variants, Magento 2 attributes that are synchronized will be marked as Used for variations, then, attribute values from the product and variants will be combined and assigned to the parnet product. Variations must have only one value for each attribute.</li>
            </ul>
        </li>
        <li>Inside categories, products and variants there will be attributes; Sales Layer Product Identification, Sales Layer Product Company Identification and Sales Layer Format Identification, don't modify or delete this attributes or its values, otherwise, the products will be created again as new ones in the next synchronization.</li>
        <li>Inside the connector configuration you can set different values before the synchronization in the different tabs, such as:
          <ul>
            <li>Auto-synchronization and preferred hour for it.</li>
            <li>The stores where the information will be updated.</li>
            <li>The root category where the incoming category branch will be set.</li>
            <li>Avoid stock update(stock will be updated only at creation of new items)</li>
            <li>Format configurable attributes(the configurable attributes for variations)</li>
          </ul>
        </li>
    </ul>
</p>
