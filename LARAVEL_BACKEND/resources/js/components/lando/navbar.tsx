"use client"

import { useState } from "react"
import Link from "next/link"
import { Button } from "@/components/ui/button"
import { Menu, X } from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { cn } from "@/lib/utils"
import type { CmsLink } from "./types"

interface LandoNavbarProps {
  links?: CmsLink[]
