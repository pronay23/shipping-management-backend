<?php

namespace App\Services;

use App\Models\Voyage;
use App\Models\BillOfLading;
use XMLWriter;

class ManifestXmlService
{
    public function generateIgm(Voyage $voyage): string
    {
        return $this->generateManifest($voyage, 'igm');
    }

    public function generateEgm(Voyage $voyage): string
    {
        return $this->generateManifest($voyage, 'egm');
    }

    protected function generateManifest(Voyage $voyage, string $type): string
    {
        $voyage->load('billOfLadings.containers');
        $bols = $voyage->billOfLadings;

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('');
        $xml->startDocument('1.0', 'UTF-8');
        
        $xml->startElement('Awmds');

        // General_segment
        $xml->startElement('General_segment');
        
        $xml->startElement('General_segment_id');
        $xml->writeElement('Customs_office_code', $voyage->customs_office_code ?? '301');
        $xml->writeElement('Voyage_number', $voyage->voyage_number);
        $xml->writeElement('Date_of_departure', $voyage->departure_date ? $voyage->departure_date->format('Y-m-d') : '');
        $xml->writeElement('Date_of_arrival', $voyage->arrival_date ? $voyage->arrival_date->format('Y-m-d') : '');
        $xml->endElement(); // General_segment_id

        $totalPackages = 0;
        $totalContainers = 0;
        $totalGrossMass = 0.0;
        
        foreach ($bols as $bol) {
            $totalPackages += (int) $bol->total_net_weight_mt; // Placeholder for packages/bags, might need adjustment based on data
            $totalContainers += $bol->containers->count();
            $totalGrossMass += (float) $bol->total_gross_weight_mt;
        }

        $xml->startElement('Totals_segment');
        $xml->writeElement('Total_number_of_bols', $bols->count());
        $xml->writeElement('Total_number_of_packages', $totalPackages);
        $xml->writeElement('Total_number_of_containers', $totalContainers);
        $xml->writeElement('Total_gross_mass', number_format($totalGrossMass, 2, '.', ''));
        $xml->endElement(); // Totals_segment

        $xml->startElement('Transport_information');
        $xml->startElement('Carrier');
        $xml->writeElement('Carrier_code', $voyage->carrier_code ?? '301043077');
        $xml->writeElement('Carrier_name', $voyage->carrier_name ?? 'BANGLADESH CONTAINER LINES LIMITED');
        $xml->writeElement('Carrier_address', $voyage->carrier_address ?? '36, JOY BANGLA TOWER 12TH FLOOR, AGRABAD C/A, CHATTOGRAM, BANGLADESH');
        $xml->endElement(); // Carrier
        $xml->writeElement('Mode_of_transport_code', $voyage->mode_of_transport_code ?? '1');
        $xml->writeElement('Identity_of_transporter', $voyage->vessel_name);
        $xml->writeElement('Nationality_of_transporter_code', $voyage->nationality_of_transporter_code ?? 'BD');
        $xml->endElement(); // Transport_information

        $xml->startElement('Load_unload_place');
        $xml->writeElement('Place_of_departure_code', $voyage->port_of_loading ?? '');
        $xml->writeElement('Place_of_destination_code', $voyage->port_of_discharge ?? '');
        $xml->endElement(); // Load_unload_place

        $xml->endElement(); // General_segment

        $lineNumber = 1;
        foreach ($bols as $bol) {
            $xml->startElement('Bol_segment');
            
            $xml->startElement('Bol_id');
            $xml->writeElement('Bol_reference', $bol->bill_number);
            $xml->writeElement('Line_number', $lineNumber++);
            $xml->writeElement('Bol_nature', $bol->bol_nature ?? '23');
            $xml->writeElement('Bol_type_code', $bol->bol_type_code ?? 'HSB');
            $xml->writeElement('DG_status', '');
            $xml->endElement(); // Bol_id

            $xml->writeElement('Consolidated_Cargo', $bol->consolidated_cargo ?? '0');
            
            $xml->startElement('Load_unload_place');
            $xml->writeElement('Port_of_origin_code', $bol->port_of_loading);
            $xml->writeElement('Place_of_unloading_code', $bol->port_of_discharge);
            $xml->endElement(); // Load_unload_place
            
            $xml->startElement('Traders_segment');
            $xml->startElement('Carrier');
            $xml->writeElement('Carrier_code', $voyage->carrier_code ?? '301043077');
            $xml->writeElement('Carrier_name', $voyage->carrier_name ?? 'BANGLADESH CONTAINER LINES LIMITED');
            $xml->writeElement('Carrier_address', $voyage->carrier_address ?? '36, JOY BANGLA TOWER 12TH FLOOR, AGRABAD C/A, CHATTOGRAM, BANGLADESH');
            $xml->endElement();

            $xml->startElement('Shipping_Agent');
            $xml->writeElement('Shipping_Agent_code', $bol->shipping_agent_code ?? 'SLA');
            $xml->writeElement('Shipping_Agent_name', $bol->shipping_agent_name ?? 'SPRING SEA SHIPPING LINES AGENTS AG');
            $xml->endElement();

            $xml->startElement('Exporter');
            $xml->writeElement('Exporter_name', $bol->exporter_name ?? substr($bol->shipper_name, 0, 70));
            $xml->writeElement('Exporter_address', $bol->exporter_address ?? substr($bol->shipper_name, 0, 150));
            $xml->endElement();

            $xml->startElement('Notify');
            $xml->writeElement('Notify_code', $bol->notify_code ?? '');
            $xml->writeElement('Notify_name', $bol->notify_name ?? substr($bol->notify_party, 0, 70));
            $xml->writeElement('Notify_address', $bol->notify_address ?? substr($bol->notify_party, 0, 150));
            $xml->endElement();

            $xml->startElement('Consignee');
            $xml->writeElement('Consignee_code', $bol->consignee_code ?? '');
            $xml->writeElement('Consignee_name', $bol->consignee_name ?? substr($bol->consignee_name, 0, 70));
            $xml->writeElement('Consignee_address', $bol->consignee_address ?? substr($bol->consignee_name, 0, 150));
            $xml->endElement();
            $xml->endElement(); // Traders_segment

            foreach ($bol->containers as $container) {
                $xml->startElement('ctn_segment');
                $xml->writeElement('Ctn_reference', $container->container_no);
                $xml->writeElement('Number_of_packages', $container->bags);
                $xml->writeElement('Type_of_container', $container->type_of_container ?? '45G1');
                $xml->writeElement('Status', $container->status ?? 'FCL');
                $xml->writeElement('Seal_number', $container->seal_no);
                $xml->writeElement('IMCO', '');
                $xml->writeElement('UN', '');
                $xml->writeElement('Ctn_location', '');
                $xml->writeElement('Commodity_code', $container->commodity_code ?? '35');
                $xml->writeElement('Gross_weight', number_format((float)$container->gross_weight_kgs, 2, '.', ''));
                $xml->endElement(); // ctn_segment
            }

            $xml->startElement('Goods_segment');
            $xml->writeElement('Number_of_packages', (int) $bol->total_net_weight_mt); // Usually package count
            $xml->writeElement('Package_type_code', $bol->package_type_code ?? 'BG');
            $xml->writeElement('Gross_mass', number_format((float)$bol->total_gross_weight_mt, 2, '.', ''));
            $xml->writeElement('Shipping_marks', $bol->shipping_marks ?? 'N/M');
            $xml->writeElement('Goods_description', substr($bol->product_name, 0, 250));
            $xml->writeElement('Volume_in_cubic_meters', $bol->volume_in_cubic_meters ?? '0');
            $xml->writeElement('Num_of_ctn_for_this_bol', $bol->containers->count());
            $xml->writeElement('Remarks', 'COC');
            $xml->endElement(); // Goods_segment

            $xml->startElement('Value_segment');
            $xml->startElement('Freight_segment');
            $xml->writeElement('Freight_value', $bol->freight_value ?? '0');
            $xml->writeElement('Freight_currency', $bol->freight_currency ?? 'ZZZ');
            $xml->endElement(); // Freight_segment
            $xml->endElement(); // Value_segment

            $xml->endElement(); // Bol_segment
        }

        $xml->endElement(); // Awmds
        $xml->endDocument();

        return $xml->outputMemory(true);
    }
}
