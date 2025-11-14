<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CheckNewSlots extends Command
{
    /**
     * Poke API Key
     */
    private const POKE_API_KEY = 'pk_6k52dHi20XUJm8o83xVP4QwbUJbttHLwhtDx3Ogd0Fg';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-new-slots';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for available booking slots on TMR website';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $url = 'https://www.service.transport.qld.gov.au/SBSExternal/public/WelcomeDrivingTest.xhtml?dswid=-7969';

        $this->info('Navigating to TMR website...');

        $this->browse(function ($browser) use ($url) {
            $browser->visit($url);
            
            // Wait for page to load - give it time for JavaScript to execute
            $browser->pause(5000); // Wait for page to fully load, including any JavaScript

            $this->info('Page loaded. Looking for Continue button...');

            // Try to find and click the Continue button using JavaScript
            try {
                $clicked = $browser->script("
                    var buttons = document.querySelectorAll('button, input[type=\"submit\"], input[type=\"button\"], a');
                    for (var i = 0; i < buttons.length; i++) {
                        var text = (buttons[i].textContent || buttons[i].value || '').trim().toLowerCase();
                        if (text.includes('continue')) {
                            buttons[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
                            buttons[i].click();
                            return true;
                        }
                    }
                    return false;
                ");

                if ($clicked[0]) {
                    $this->info('Continue button clicked. Waiting for page to load...');
                    $browser->pause(500); // Small pause after click
                    $browser->pause(3000); // Wait for next page to load
                } else {
                    $this->warn('Could not find Continue button');
                }
            } catch (\Exception $e) {
                $this->warn('Error clicking Continue button: ' . $e->getMessage());
            }

            // Check for and click Accept button if present
            $this->info('Checking for Accept button...');
            try {
                $acceptClicked = $browser->script("
                    var buttons = document.querySelectorAll('button, input[type=\"submit\"], input[type=\"button\"], a');
                    for (var i = 0; i < buttons.length; i++) {
                        var text = (buttons[i].textContent || buttons[i].value || '').trim().toLowerCase();
                        if (text.includes('accept')) {
                            buttons[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
                            buttons[i].click();
                            return true;
                        }
                    }
                    return false;
                ");

                if ($acceptClicked[0]) {
                    $this->info('Accept button clicked. Waiting for page to load...');
                    $browser->pause(500); // Small pause after click
                    $browser->pause(3000); // Wait for next page to load
                } else {
                    $this->info('No Accept button found, continuing...');
                }
            } catch (\Exception $e) {
                $this->warn('Error checking for Accept button: ' . $e->getMessage());
            }

            // Check for and click the product type select dropdown
            $this->info('Checking for product type select...');
            try {
                // First check if the select exists
                $selectExists = $browser->script("
                    return document.getElementById('CleanBookingDEForm:productType') !== null;
                ");

                if ($selectExists[0]) {
                    $this->info('Product type select found. Attempting to click...');
                    
                    // Try using Dusk's click method with the ID selector
                    try {
                        $browser->click('#CleanBookingDEForm\\:productType');
                        $this->info('Clicked select using Dusk click method');
                    } catch (\Exception $e1) {
                        // Try with attribute selector
                        try {
                            $browser->click('[id="CleanBookingDEForm:productType"]');
                            $this->info('Clicked select using attribute selector');
                        } catch (\Exception $e2) {
                            // Fall back to JavaScript
                            $browser->script("
                                var select = document.getElementById('CleanBookingDEForm:productType');
                                if (select) {
                                    select.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    
                                    // Try multiple event types
                                    var events = ['mousedown', 'mouseup', 'click', 'focus'];
                                    events.forEach(function(eventType) {
                                        var event = new MouseEvent(eventType, {
                                            view: window,
                                            bubbles: true,
                                            cancelable: true
                                        });
                                        select.dispatchEvent(event);
                                    });
                                    
                                    // Also try clicking any trigger element
                                    var trigger = select.querySelector('.ui-selectonemenu-trigger') || 
                                                  select.nextElementSibling;
                                    if (trigger) {
                                        trigger.click();
                                    }
                                }
                            ");
                            $this->info('Clicked select using JavaScript fallback');
                        }
                    }
                    
                    $this->info('Waiting for dropdown to open...');
                    $browser->pause(2000); // Wait longer for dropdown to open
                    
                    // Wait for the dropdown items to appear - try multiple selectors
                    $dropdownOpened = false;
                    $selectors = [
                        '[id="CleanBookingDEForm:productType_items"]',
                        '.ui-selectonemenu-items',
                        '.ui-selectonemenu-list',
                        '[role="listbox"]'
                    ];
                    
                    foreach ($selectors as $selector) {
                        try {
                            $browser->waitFor($selector, 3);
                            $this->info('Dropdown opened successfully (found with: ' . $selector . ')');
                            $dropdownOpened = true;
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    
                    if (!$dropdownOpened) {
                        $this->warn('Dropdown items did not appear, but continuing...');
                    } else {
                        // Select the first option from the dropdown
                        $this->info('Selecting first option from dropdown...');
                        try {
                            $selected = $browser->script("
                                // Try to find the first option by ID first
                                var firstOption = document.getElementById('CleanBookingDEForm:productType_1');
                                if (firstOption) {
                                    firstOption.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    firstOption.click();
                                    return true;
                                }
                                
                                // Fallback: find first selectable option (skip placeholder)
                                var itemsContainer = document.getElementById('CleanBookingDEForm:productType_items');
                                if (itemsContainer) {
                                    var items = itemsContainer.querySelectorAll('li.ui-selectonemenu-item');
                                    for (var i = 0; i < items.length; i++) {
                                        var item = items[i];
                                        var text = item.textContent.trim();
                                        // Skip placeholder items
                                        if (!text.includes('Please select') && !text.includes('---')) {
                                            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            item.click();
                                            return true;
                                        }
                                    }
                                }
                                return false;
                            ");
                            if ($selected[0]) {
                                $this->info('First option selected (Class C/CA - Car)');
                                $browser->pause(1000); // Wait for selection to register
                            } else {
                                $this->warn('Could not find dropdown items to select');
                            }
                        } catch (\Exception $e) {
                            $this->warn('Error selecting first option: ' . $e->getMessage());
                        }
                    }
                    
                    $browser->pause(1000); // Additional pause
                } else {
                    $this->info('Product type select not found, continuing...');
                }
            } catch (\Exception $e) {
                $this->warn('Error clicking product type select: ' . $e->getMessage());
            }

            // Fill in the form fields
            $this->info('Filling in form fields...');
            
            // Fill DL Number
            try {
                $browser->type('#CleanBookingDEForm\\:dlNumber', '138982450');
                $this->info('DL Number filled');
                $browser->pause(500);
            } catch (\Exception $e) {
                try {
                    $browser->keys('#CleanBookingDEForm\\:dlNumber', '138982450');
                    $this->info('DL Number filled (using keys method)');
                } catch (\Exception $e2) {
                    $this->warn('Error filling DL Number: ' . $e2->getMessage());
                }
            }

            // Fill Contact Name
            try {
                $browser->type('#CleanBookingDEForm\\:contactName', 'Theoneff Veda Baldecanas');
                $this->info('Contact Name filled');
                $browser->pause(500);
            } catch (\Exception $e) {
                try {
                    $browser->keys('#CleanBookingDEForm\\:contactName', 'Theoneff Veda Baldecanas');
                    $this->info('Contact Name filled (using keys method)');
                } catch (\Exception $e2) {
                    $this->warn('Error filling Contact Name: ' . $e2->getMessage());
                }
            }

            // Fill Contact Phone
            try {
                $browser->type('#CleanBookingDEForm\\:contactPhone', '0491389459');
                $this->info('Contact Phone filled');
                $browser->pause(500);
            } catch (\Exception $e) {
                try {
                    $browser->keys('#CleanBookingDEForm\\:contactPhone', '0491389459');
                    $this->info('Contact Phone filled (using keys method)');
                } catch (\Exception $e2) {
                    $this->warn('Error filling Contact Phone: ' . $e2->getMessage());
                }
            }

            // Click Continue button
            $this->info('Looking for Continue button to submit form...');
            try {
                $continueClicked = $browser->script("
                    var buttons = document.querySelectorAll('button, input[type=\"submit\"], input[type=\"button\"], a');
                    for (var i = 0; i < buttons.length; i++) {
                        var text = (buttons[i].textContent || buttons[i].value || '').trim().toLowerCase();
                        if (text.includes('continue')) {
                            buttons[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
                            buttons[i].click();
                            return true;
                        }
                    }
                    return false;
                ");

                if ($continueClicked[0]) {
                    $this->info('Continue button clicked. Waiting for confirm license details page...');
                    $browser->pause(500);
                    $browser->pause(3000);
                } else {
                    $this->warn('Could not find Continue button');
                }
            } catch (\Exception $e) {
                $this->warn('Error clicking Continue button: ' . $e->getMessage());
            }

            // Click Continue on confirm license details page
            $this->info('Looking for Continue button on confirm license details page...');
            try {
                $continueClicked = $browser->script("
                    var buttons = document.querySelectorAll('button, input[type=\"submit\"], input[type=\"button\"], a');
                    for (var i = 0; i < buttons.length; i++) {
                        var text = (buttons[i].textContent || buttons[i].value || '').trim().toLowerCase();
                        if (text.includes('continue')) {
                            buttons[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
                            buttons[i].click();
                            return true;
                        }
                    }
                    return false;
                ");

                if ($continueClicked[0]) {
                    $this->info('Continue button clicked on confirm page. Waiting for region selection page...');
                    $browser->pause(500);
                    $browser->pause(3000);
                } else {
                    $this->warn('Could not find Continue button on confirm page');
                }
            } catch (\Exception $e) {
                $this->warn('Error clicking Continue button on confirm page: ' . $e->getMessage());
            }

            // Find and click the region select dropdown
            $this->info('Looking for region select dropdown...');
            try {
                // First check if the region select exists
                $regionSelectExists = $browser->script("
                    return document.getElementById('BookingSearchForm:region') !== null;
                ");

                if ($regionSelectExists[0]) {
                    $this->info('Region select found. Attempting to click...');
                    
                    // Try using Dusk's click method with the ID selector
                    try {
                        $browser->click('#BookingSearchForm\\:region');
                        $this->info('Clicked region select using Dusk click method');
                    } catch (\Exception $e1) {
                        // Try with attribute selector
                        try {
                            $browser->click('[id="BookingSearchForm:region"]');
                            $this->info('Clicked region select using attribute selector');
                        } catch (\Exception $e2) {
                            // Fall back to JavaScript
                            $browser->script("
                                var select = document.getElementById('BookingSearchForm:region');
                                if (select) {
                                    select.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    select.click();
                                }
                            ");
                            $this->info('Clicked region select using JavaScript fallback');
                        }
                    }
                    
                    $this->info('Waiting for region dropdown to open...');
                    $browser->pause(2000);
                    
                    // Wait for the dropdown items to appear
                    $dropdownOpened = false;
                    $selectors = [
                        '[id="BookingSearchForm:region_items"]',
                        '.ui-selectonemenu-items',
                        '[role="listbox"]'
                    ];
                    
                    foreach ($selectors as $selector) {
                        try {
                            $browser->waitFor($selector, 3);
                            $this->info('Region dropdown opened successfully');
                            $dropdownOpened = true;
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    
                    if ($dropdownOpened) {
                        // Select "SEQ BRISBANE SOUTHSIDE" option
                        $this->info('Selecting SEQ BRISBANE SOUTHSIDE...');
                        try {
                            $selected = $browser->script("
                                // Try to find the option by ID first
                                var option = document.getElementById('BookingSearchForm:region_13');
                                if (option) {
                                    option.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    option.click();
                                    return true;
                                }
                                
                                // Fallback: find by text content
                                var itemsContainer = document.getElementById('BookingSearchForm:region_items');
                                if (!itemsContainer) {
                                    itemsContainer = document.querySelector('.ui-selectonemenu-items');
                                }
                                if (itemsContainer) {
                                    var items = itemsContainer.querySelectorAll('li.ui-selectonemenu-item');
                                    for (var i = 0; i < items.length; i++) {
                                        var item = items[i];
                                        var text = item.textContent.trim();
                                        if (text.includes('SEQ BRISBANE SOUTHSIDE')) {
                                            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            item.click();
                                            return true;
                                        }
                                    }
                                }
                                return false;
                            ");
                            
                            if ($selected[0]) {
                                $this->info('SEQ BRISBANE SOUTHSIDE selected');
                                $browser->pause(1000);
                            } else {
                                $this->warn('Could not select SEQ BRISBANE SOUTHSIDE');
                            }
                        } catch (\Exception $e) {
                            $this->warn('Error selecting region: ' . $e->getMessage());
                        }
                    }
                    
                    $browser->pause(1000);
                } else {
                    $this->warn('Region select not found');
                }
            } catch (\Exception $e) {
                $this->warn('Error finding region select: ' . $e->getMessage());
            }

            // Click Continue after region selection
            $this->info('Looking for Continue button after region selection...');
            try {
                $continueClicked = $browser->script("
                    var buttons = document.querySelectorAll('button, input[type=\"submit\"], input[type=\"button\"], a');
                    for (var i = 0; i < buttons.length; i++) {
                        var text = (buttons[i].textContent || buttons[i].value || '').trim().toLowerCase();
                        if (text.includes('continue')) {
                            buttons[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
                            buttons[i].click();
                            return true;
                        }
                    }
                    return false;
                ");

                if ($continueClicked[0]) {
                    $this->info('Continue button clicked after region selection. Waiting for page to load...');
                    $browser->pause(500);
                    $browser->pause(3000);
                } else {
                    $this->warn('Could not find Continue button after region selection');
                }
            } catch (\Exception $e) {
                $this->warn('Error clicking Continue button after region selection: ' . $e->getMessage());
            }

            // Wait for the booking table to load
            $this->info('Waiting for booking table to load...');
            try {
                $browser->waitFor('.ui-datatable-tablewrapper', 10);
                $browser->pause(2000); // Additional pause for table to fully render
            } catch (\Exception $e) {
                $this->warn('Booking table did not appear: ' . $e->getMessage());
            }

            // Extract first row data from the table
            $this->info('Extracting booking information from table...');
            try {
                $bookingData = $browser->script("
                    // Find the table wrapper
                    var tableWrapper = document.querySelector('.ui-datatable-tablewrapper');
                    if (!tableWrapper) {
                        return null;
                    }
                    
                    // Find all rows - try multiple selectors
                    var rows = tableWrapper.querySelectorAll('tbody tr');
                    if (rows.length === 0) {
                        rows = tableWrapper.querySelectorAll('tr[role=\"row\"]');
                    }
                    if (rows.length === 0) {
                        rows = tableWrapper.querySelectorAll('tr');
                    }
                    
                    // Skip header row if it exists
                    var firstDataRow = null;
                    for (var i = 0; i < rows.length; i++) {
                        var row = rows[i];
                        var cells = row.querySelectorAll('td[role=\"gridcell\"]');
                        if (cells.length >= 2) {
                            firstDataRow = row;
                            break;
                        }
                    }
                    
                    if (!firstDataRow) {
                        return null;
                    }
                    
                    var cells = firstDataRow.querySelectorAll('td[role=\"gridcell\"]');
                    if (cells.length < 2) {
                        return null;
                    }
                    
                    // Identify which cell is booking time and which is location
                    var bookingTime = '';
                    var location = '';
                    
                    for (var i = 0; i < cells.length; i++) {
                        var cellText = cells[i].textContent.trim();
                        // Check if this cell contains a date/time pattern
                        if (cellText.match(/(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday).*\d{1,2}:\d{2}\s*(AM|PM)/i) ||
                            cellText.match(/\d{1,2}\s+(January|February|March|April|May|June|July|August|September|October|November|December).*\d{1,2}:\d{2}\s*(AM|PM)/i)) {
                            bookingTime = cellText;
                        } else if (cellText.includes('CSC') || (!bookingTime && cellText.length > 0)) {
                            location = cellText;
                        }
                    }
                    
                    // Fallback: use first two cells in order if we couldn't identify
                    if (!bookingTime && cells.length > 0) {
                        bookingTime = cells[0].textContent.trim();
                    }
                    if (!location && cells.length > 1) {
                        location = cells[1].textContent.trim();
                    }
                    
                    return {
                        bookingTime: bookingTime,
                        location: location
                    };
                ");
                
                if (!empty($bookingData) && isset($bookingData[0]) && isset($bookingData[0]['bookingTime'])) {
                    $bookingTime = $bookingData[0]['bookingTime'];
                    $location = $bookingData[0]['location'];
                    
                    $this->line('');
                    $this->info('=== BOOKING FOUND ===');
                    $this->line("Available Booking Time: {$bookingTime} | Location: {$location}");
                    $this->line('');
                    
                    // Send booking info to Poke with instructions to check date and respond accordingly
                    $message = "Available Booking Time: {$bookingTime} | Location: {$location}\n\nCan you identify if this is before end of november, if yes. Can you nag me about it. If not, just say there is one upcoming";
                    
                    $this->info('Sending notification to Poke API...');
                    try {
                        $ch = curl_init('https://poke.com/api/v1/inbound-sms/webhook');
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST => true,
                            CURLOPT_HTTPHEADER => [
                                'Authorization: Bearer ' . self::POKE_API_KEY,
                                'Content-Type: application/json',
                            ],
                            CURLOPT_POSTFIELDS => json_encode([
                                'message' => $message,
                            ]),
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error = curl_error($ch);
                        curl_close($ch);
                        
                        if ($error) {
                            $this->warn('Error sending to Poke API: ' . $error);
                        } elseif ($httpCode >= 200 && $httpCode < 300) {
                            $this->info('Successfully sent notification to Poke API');
                            if ($response) {
                                $this->line('Response: ' . $response);
                            }
                        } else {
                            $this->warn('Poke API returned HTTP ' . $httpCode);
                            if ($response) {
                                $this->line('Response: ' . $response);
                            }
                        }
                    } catch (\Exception $e) {
                        $this->warn('Error sending to Poke API: ' . $e->getMessage());
                    }
                } else {
                    $this->warn('Could not extract booking data from table');
                }
            } catch (\Exception $e) {
                $this->warn('Error extracting booking information: ' . $e->getMessage());
            }
        });
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
