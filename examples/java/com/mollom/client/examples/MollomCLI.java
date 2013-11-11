package com.mollom.client.examples;

import com.mollom.client.Captcha;
import com.mollom.client.CaptchaType;
import com.mollom.client.Content;
import com.mollom.client.MollomClient;
import com.mollom.client.MollomClientBuilder;
import com.mollom.client.MollomException;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;

public class MollomCLI {
  public void singleRequest() throws IOException, MollomException {
    BufferedReader bufferedReader = new BufferedReader(new InputStreamReader(System.in));

    // Get the user's public and private keys
    System.out.println("What is your public key?");
    String publicKey = bufferedReader.readLine();
    System.out.println("What is your private key?");
    String privateKey = bufferedReader.readLine();

    // Build the client object
    MollomClient mollomClient = MollomClientBuilder.create().build(publicKey, privateKey);

    // Build the content object to check
    Content content = new Content();

    System.out.println("What is your post's title?");
    String postTile = bufferedReader.readLine();
    content.setPostTitle(postTile);
    System.out.println("What is your post's body?");
    String postBody = bufferedReader.readLine();
    content.setPostBody(postBody);

    mollomClient.checkContent(content);

    if (content.isHam()) {
      System.out.println("Content is HAM!");
    } else if (content.isSpam()) {
      System.out.println("Content is SPAM!");
    } else { // Mollom is unsure, prompt with a CAPTCHA
      Captcha captcha = mollomClient.createCaptcha(CaptchaType.IMAGE, false, content);

      System.out.println("Please solve this CAPTCHA: " + captcha.getUrl());
      String captchaSolution = bufferedReader.readLine();
      captcha.setSolution(captchaSolution);
      mollomClient.checkCaptcha(captcha);

      if (captcha.isSolved()) {
        System.out.println("Content is HAM!");
      } else {
        System.out.println("Content is SPAM!");
      }
    }

    // Cleanup connection
    mollomClient.destroy();
  }

  public static void main(String[] args) {
    MollomCLI cli = new MollomCLI();
    try {
      cli.singleRequest();
    } catch (IOException e) {
      e.printStackTrace();
    } catch (MollomException e) {
      e.printStackTrace();
    }
  }
}
